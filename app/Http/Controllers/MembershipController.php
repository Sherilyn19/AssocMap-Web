<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\MembershipRuleException;
use App\Http\Requests\Membership\MemberFiltersRequest;
use App\Http\Requests\Membership\ReviewMemberApplicationRequest;
use App\Http\Requests\Membership\SetReviewPassphraseRequest;
use App\Http\Requests\Membership\SubmitMemberApplicationRequest;
use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Services\MembershipAccess;
use App\Services\MembershipWorkflowService;
use App\Services\SessionUserResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/** HTTP boundary: authorize, delegate the transaction, then explain its outcome safely. */
final class MembershipController extends Controller
{
    public function __construct(
        private readonly SessionUserResolver $sessionUser,
        private readonly MembershipAccess $access,
        private readonly MembershipWorkflowService $workflow,
    ) {
    }

    public function index(MemberFiltersRequest $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('viewAny', MemberApplication::class);
        // Saved drafts use the same Members workspace, with their own authorization.
        if ($request->input('tab') === 'drafts') {
            abort_unless($actor->role?->role_name === 'Field Officer', 403);

            Gate::forUser($actor)->authorize('viewAny', \App\Models\MemberDraft::class);

            $associationOptions = Association::query()
                ->where('field_officer_id', $actor->id)
                ->orderBy('name')
                ->get(['id', 'name']);

            $drafts = \App\Models\MemberDraft::query()
                ->with('association')
                ->where('created_by_user_id', $actor->id)
                ->whereHas('association', fn ($query) =>
                    $query->where('field_officer_id', $actor->id)
                );

            if ($request->filled('association_id')) {
                $associationId = $request->integer('association_id');

                abort_unless($associationOptions->contains('id', $associationId), 403);
                $drafts->where('association_id', $associationId);
            }

            if ($request->filled('draft_state')) {
                $drafts->where('state', $request->input('draft_state'));
            }

            if ($request->filled('search')) {
                // Search only the already-authorized drafts.
                $drafts->whereRaw(
                    "CONCAT_WS(' ', profile->>'first_name', profile->>'middle_name',
                    profile->>'last_name') ILIKE ?",
                    ['%'.$request->input('search').'%']
                );
            }

            return view('field-officer-user.members.index', [
                'associationOptions' => $associationOptions,
                'drafts' => $drafts->orderByDesc('updated_at')
                    ->orderByDesc('id')->paginate(15)->withQueryString(),
            ]);
        }
        $applications = $this->access->scope(MemberApplication::with(['association', 'status']), $actor);
        // Apply association access first. Archive filters never expand officer access.
        $members = $this->access->scope(Member::with('association'), $actor);

        if ($actor->role?->role_name === 'Field Officer') {
            // Default to current members, including when the filter is blank.
            $recordState = $request->input('record_state') ?: 'current';

            if ($recordState === 'archived') {
                $members->where('is_archived', true);
            } elseif ($recordState !== 'all') {
                $members->where('is_archived', false);
            }
        } else {
            // Preserve the existing register behavior for other roles.
            $members->where('is_archived', false);
        }
        if ($request->filled('status')) {
            $applications->whereHas('status', fn ($query) => $query->where('status_name', $request->input('status')));
        }
        if ($request->filled('search')) {
            foreach ([$applications, $members] as $query) {
                $query->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) ILIKE ?", ['%'.$request->input('search').'%']);
            }
        }

        $associationOptions = collect();

        if ($actor->role?->role_name === 'Field Officer') {
            // Offer only associations currently assigned to this officer.
            $associationOptions = Association::query()
                ->where('field_officer_id', $actor->id)
                ->orderBy('name')
                ->get(['id', 'name']);

            if ($request->filled('association_id')) {
                $associationId = $request->integer('association_id');

                // A manually changed URL must not select another officer's association.
                abort_unless(
                    $associationOptions->contains('id', $associationId),
                    403
                );

                $members->where('association_id', $associationId);
                $applications->where('association_id', $associationId);
            }

            // Roles belong to official members, not application records.
            if ($request->filled('role_in_assoc')) {
                $members->where('role_in_assoc', $request->input('role_in_assoc'));
            }

            if ($request->filled('representative')) {
                $isRepresentative = $request->input('representative') === 'yes';

                $members->whereHas('association', function ($query) use ($isRepresentative) {
                    if ($isRepresentative) {
                        $query->whereColumn(
                            'associations.representative_member_id',
                            'members.id'
                        );
                    } else {
                        // If nobody is designated, every member is a non-representative.
                        $query->where(function ($designation) {
                            $designation->whereNull('associations.representative_member_id')
                                ->orWhereColumn(
                                    'associations.representative_member_id',
                                    '<>',
                                    'members.id'
                                );
                        });
                    }
                });
            }
        }
        // Field Officers use their own presentation with the same scoped records.
        return view($actor->role?->role_name === 'Field Officer'
            ? 'field-officer-user.members.index'
            : 'shared.membership.index', [
            'applications' => $applications->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'members' => $members->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(15, ['*'], 'members_page')->withQueryString(),
            'canSubmit' => Gate::forUser($actor)->allows('create', MemberApplication::class),
            'associationOptions' => $associationOptions,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('create', MemberApplication::class);
        return view('shared.membership.create', [
            'association' => Association::findOrFail($actor->association_id),
            'sexOptions' => DB::table('sex')->orderBy('sex_name')->get(),
        ]);
    }

    public function store(SubmitMemberApplicationRequest $request): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        return $this->mutate($request, function () use ($request, $actor): RedirectResponse {
            $application = $this->workflow->submit($actor, $request->validated());
            return redirect()->route('membership.applications.show', $application)->with('success', 'Representative verified. Application approved and official member registered..');
        });
    }

    public function show(Request $request, MemberApplication $application): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $application);
        // The Field Officer dialog consumes the same authorized record as the full page.
        return view($actor->role?->role_name === 'Field Officer'
            ? 'field-officer-user.members.record'
            : 'shared.membership.application', [
            'application' => $application->load(['association.representative', 'status', 'sex', 'reviewer', 'member']),
            'canReview' => app(\App\Services\RepresentativeReviewAccess::class)->allows($request, $actor, $application),
            'canUnlockReview' => Gate::forUser($actor)->allows('review', $application),
        ]);
    }

    public function member(Request $request, Member $member): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $member);
        // Reuse the loaded profile without exposing editing or approval controls.
        return view($actor->role?->role_name === 'Field Officer'
            ? 'field-officer-user.members.record'
            : 'shared.membership.member', [
                'member' => $member->load(['association', 'sex']),
            ]);
    }

    public function unlockReview(Request $request, MemberApplication $application): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('review', $application);
        $data = $request->validate(['review_passphrase' => ['required', 'string', 'max:72']]);
        $allowed = app(\App\Services\RepresentativeReviewAccess::class)->unlock($request, $actor, $application, $data['review_passphrase']);
        return redirect()->route('membership.applications.show', $application)
            ->with($allowed ? 'success' : 'error', $allowed
                ? 'Representative verified. Review access expires in 10 minutes. Confirm your private passphrase again when recording the decision.'
                : 'Review access could not be verified. Use the current representative’s private passphrase.');
    }

    public function review(ReviewMemberApplicationRequest $request, MemberApplication $application): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        return $this->mutate($request, function () use ($request, $actor, $application): RedirectResponse {
            $this->workflow->review($actor, $application, $request->validated());
            $request->session()->forget('representative_review');
            return redirect()->route('membership.applications.show', $application)->with('success', 'Review recorded successfully.');
        });
    }

    public function credential(SetReviewPassphraseRequest $request, Member $member): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        return $this->mutate($request, function () use ($request, $actor, $member): RedirectResponse {
            $this->workflow->setReviewPassphrase($actor, $member, $request->validated('review_passphrase'));
            return back()->with('success', 'Review passphrase saved. Communicate it privately to the designated representative.');
        });
    }

    private function mutate(Request $request, \Closure $operation): RedirectResponse
    {
        try {
            return $operation();
        } catch (MembershipRuleException $exception) {
            // Only our explicit business-rule messages are suitable for user display.
            return back()->withInput($request->except(['review_passphrase', 'review_passphrase_confirmation']))->with('error', $exception->getMessage());
        } catch (QueryException $exception) {
            report($exception);
            $message = $exception->getCode() === '23505'
                ? 'This application or member already exists. Refresh the list before trying again.'
                : 'The change could not be saved. Please try again.';
            return back()->withInput($request->except(['review_passphrase', 'review_passphrase_confirmation']))->with('error', $message);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withInput($request->except(['review_passphrase', 'review_passphrase_confirmation']))->with('error', 'The request could not be completed. Please try again.');
        }
    }
}
