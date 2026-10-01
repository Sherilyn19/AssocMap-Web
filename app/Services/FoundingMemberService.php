<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MembershipRuleException;
use App\Models\Association;
use App\Models\Member;
use App\Models\User;
use App\Support\MemberProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** The approved one-member exception establishes eligibility for later designation. */
final class FoundingMemberService
{
    public function create(User $actor, Association $association, array $data): Member
    {
        return DB::transaction(function () use ($actor, $association, $data): Member {
            // Match account management before locking association, actor and member.
            DB::table('roles')->where('role_name', 'System Administrator')->lockForUpdate()->first();
            $association = Association::query()->lockForUpdate()->findOrFail($association->id);
            $actor = User::with('role')->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('update', $association);
            $this->requireEmptyAssociation($association);
            $data = Validator::make(MemberProfile::normalize($data), MemberProfile::rules() + [
                'justification' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
                'profile_verified' => ['accepted'],
            ])->validate();

            // No historical application or representative appointment is fabricated.
            $member = Member::create(Arr::only($data, MemberProfile::FIELDS) + [
                'association_id' => $association->id, 'application_id' => null,
                'date_registered' => now()->toDateString(), 'role_in_assoc' => 'Member',
                'is_archived' => false,
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $actor->id, 'action_type' => 'CREATE_FOUNDING_MEMBER',
                'module' => 'Member', 'record_id' => $member->id,
                'details' => json_encode(['association_id' => $association->id,
                    'profile_verified' => true, 'justification' => trim($data['justification'])], JSON_THROW_ON_ERROR),
                'performed_at' => now(),
            ]);

            return $member;
        }, 3);
    }

    public function requireEmptyAssociation(Association $association): void
    {
        // Archived members count too: archiving must never reopen the exception.
        if ($association->is_archived || $association->representative_member_id !== null
            || $association->members()->exists()) {
            throw new MembershipRuleException('Founding-member registration is available only for a current association with no official members or representative.');
        }
    }
}
