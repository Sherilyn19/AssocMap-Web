<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/** A short-lived UI unlock never replaces the workflow's transactional secret check. */
final class RepresentativeReviewAccess
{
    public function unlock(Request $request, User $actor, MemberApplication $application, string $secret): bool
    {
        $request->session()->forget('representative_review');
        $member = $application->association?->representative;
        if (!Gate::forUser($actor)->allows('review', $application) || !$member || $member->is_archived
            || (int) $member->association_id !== (int) $actor->association_id
            || !$member->review_passphrase_hash || !Hash::check($secret, $member->review_passphrase_hash)) {
            return false;
        }
        $request->session()->put('representative_review', [
            'application' => $application->id, 'proof' => $this->proof($actor, $application), 'expires' => time() + 600,
        ]);
        return true;
    }

    public function allows(Request $request, User $actor, MemberApplication $application): bool
    {
        $unlock = $request->session()->get('representative_review', []);
        return Gate::forUser($actor)->allows('review', $application)
            && ($unlock['application'] ?? null) === $application->id
            && ($unlock['expires'] ?? 0) > time()
            && hash_equals($this->proof($actor, $application), (string) ($unlock['proof'] ?? ''));
    }

    private function proof(User $actor, MemberApplication $application): string
    {
        $member = $application->association?->representative;
        return hash_hmac('sha256', implode('|', [$actor->id, $actor->password, $actor->association_id,
            $member?->id, $member?->review_passphrase_hash, (int) $member?->is_archived]), (string) config('app.key'));
    }
}
