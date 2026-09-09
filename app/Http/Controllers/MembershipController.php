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
        $applications = $this->access->scope(MemberApplication::with(['association', 'status']), $actor);
        $members = $this->access->scope(Member::with('association')->where('is_archived', false), $actor);
        if ($request->filled('status')) {
            $applications->whereHas('status', fn ($query) => $query->where('status_name', $request->input('status')));
        }
        if ($request->filled('search')) {
            foreach ([$applications, $members] as $query) {
                $query->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) ILIKE ?", ['%'.$request->input('search').'%']);
            }
        }
        return view('membership.index', [
            'applications' => $applications->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'members' => $members->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(15, ['*'], 'members_page')->withQueryString(),
            'canSubmit' => Gate::forUser($actor)->allows('create', MemberApplication::class),
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('create', MemberApplication::class);
        return view('membership.create', [
            'association' => Association::findOrFail($actor->association_id),
            'sexOptions' => DB::table('sex')->orderBy('sex_name')->get(),
        ]);
    }

    public function store(SubmitMemberApplicationRequest $request): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        return $this->mutate($request, function () use ($request, $actor): RedirectResponse {
            $application = $this->workflow->submit($actor, $request->validated());
            return redirect()->route('membership.applications.show', $application)->with('success', 'Application submitted. It is Pending representative review.');
        });
    }

    public function show(Request $request, MemberApplication $application): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $application);
        return view('membership.application', [
            'application' => $application->load(['association.representative', 'status', 'sex', 'reviewer', 'member']),
            'canReview' => Gate::forUser($actor)->allows('review', $application),
        ]);
    }

    public function member(Request $request, Member $member): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $member);
        return view('membership.member', ['member' => $member->load(['association', 'sex'])]);
    }

    public function review(ReviewMemberApplicationRequest $request, MemberApplication $application): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        return $this->mutate($request, function () use ($request, $actor, $application): RedirectResponse {
            $this->workflow->review($actor, $application, $request->validated());
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
