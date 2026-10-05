<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\FieldOfficerMembershipService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MemberController extends Controller
{
    // Return JSON for drawer requests and redirects for normal page forms.
    use \App\Http\Controllers\Concerns\MemberPanelResponses;
    public function __construct(
        private readonly SessionUserResolver $resolver,
        private readonly FieldOfficerMembershipService $workflow,
    ) {
    }

    public function edit(Request $request, Member $member)
    {
        $actor = $this->resolver->resolve($request);
        Gate::forUser($actor)->authorize('update', $member);

        return view('field-officer-user.members.edit', [
            'member' => $member->load('association'),
            'canArchive' => Gate::forUser($actor)->allows('archive', $member),
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $actor = $this->resolver->resolve($request);
        Gate::forUser($actor)->authorize('update', $member);

        return $this->write(function () use ($actor, $member, $request) {
            $this->workflow->updateMember($actor, $member, $request->all());

            return redirect()->route('officer.members.edit', $member)
                ->with('success', 'Contact number updated.');
        });
    }

    public function archive(Request $request, Member $member)
    {
        $actor = $this->resolver->resolve($request);
        Gate::forUser($actor)->authorize('archive', $member);
        $request->validate(['confirm' => ['accepted']]);

        return $this->write(function () use ($actor, $member) {
            $this->workflow->archiveMember($actor, $member);

            return redirect()->route('membership.index', ['tab' => 'members'])
                ->with('success', 'Member archived. Historical records were retained.');
        });
    }

    private function write(\Closure $operation)
    {
        // Match the response expected by the Members workspace.
        // Keep authorization, validation, and database transactions unchanged.
        return $this->memberPanelWrite(request(), $operation);
    }
}