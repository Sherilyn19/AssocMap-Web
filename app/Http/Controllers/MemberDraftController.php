<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Association;
use App\Models\MemberDraft;
use App\Services\FieldOfficerMembershipService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class MemberDraftController extends Controller
{
    // Support modal responses while preserving normal page redirects.
    use \App\Http\Controllers\Concerns\MemberPanelResponses;

    public function __construct(
        private readonly SessionUserResolver $resolver,
        private readonly FieldOfficerMembershipService $workflow,
    ) {
    }

    public function index(Request $request)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('viewAny', MemberDraft::class);

        // Field Officers view saved drafts inside their Members workspace.
        // MembershipController applies creator and current-assignment restrictions.
        if ($actor->role?->role_name === 'Field Officer') {
            return redirect()->route('membership.index', [
                'tab' => 'drafts',
            ]);
        }

        // Administrators retain their separate, read-only draft register.
        $filters = $request->validate([
            'state' => [
                'nullable',
                Rule::in(['draft', 'submitted', 'cancelled']),
            ],
        ]);

        $query = MemberDraft::with(['association', 'creator']);

        if (!empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        return view('shared.membership.drafts.index', [
            'drafts' => $query
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),

            'canCreate' => Gate::forUser($actor)->allows(
                'create',
                MemberDraft::class
            ),
        ]);
    }

    public function create(Request $request)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('create', MemberDraft::class);

        return view('shared.membership.drafts.form', [
            'draft' => new MemberDraft(),
            'canEdit' => true,
            'blockReason' => null,

            // Only current, assigned associations are available for new drafts.
            'associations' => Association::query()
                ->where('field_officer_id', $actor->id)
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'name']),

            'sexOptions' => DB::table('sex')
                ->orderBy('sex_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('create', MemberDraft::class);

        $data = $request->validate([
            'association_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->write($request, function () use ($actor, $data, $request) {
            // The service rechecks association access inside its transaction.
            $draft = $this->workflow->create(
                $actor,
                (int) $data['association_id'],
                $request->all()
            );

            return redirect()
                ->route('membership.drafts.show', $draft)
                ->with(
                    'success',
                    'Draft saved. No application or official member was created.'
                );
        });
    }

    public function show(Request $request, MemberDraft $draft)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('view', $draft);

        $draft->load(['association.representative', 'creator']);

        $representative = $draft->association?->representative;

        // This checks reviewer readiness, not the representative's secret itself.
        $ready = $representative
            && !$representative->is_archived
            && (int) $representative->association_id === (int) $draft->association_id
            && filled($representative->review_passphrase_hash);

        return view('shared.membership.drafts.form', [
            'draft' => $draft,

            'canEdit' => Gate::forUser($actor)->allows('update', $draft),

            'blockReason' => $ready
                ? null
                : 'Submission is blocked until a current representative and review passphrase are configured.',

            'associations' => collect(),

            'sexOptions' => DB::table('sex')
                ->orderBy('sex_name')
                ->get(),
        ]);
    }

    public function update(Request $request, MemberDraft $draft)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('update', $draft);

        return $this->write($request, function () use ($actor, $draft, $request) {
            $this->workflow->update(
                $actor,
                $draft,
                $request->all(),
                $this->revision($request)
            );

            return redirect()
                ->route('membership.drafts.show', $draft)
                ->with('success', 'Draft saved.');
        });
    }

    public function cancel(Request $request, MemberDraft $draft)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('cancel', $draft);

        $request->validate([
            'confirm' => ['accepted'],
        ]);

        return $this->write($request, function () use ($actor, $draft, $request) {
            // Cancellation preserves the draft and its audit history.
            $this->workflow->cancel(
                $actor,
                $draft,
                $this->revision($request)
            );

            return redirect()
                ->route('membership.drafts.show', $draft)
                ->with(
                    'success',
                    'Draft cancelled. Its history has been retained.'
                );
        });
    }

    public function submit(Request $request, MemberDraft $draft)
    {
        $actor = $this->resolver->resolve($request);

        Gate::forUser($actor)->authorize('submit', $draft);

        $request->validate([
            'confirm' => ['accepted'],
        ]);

        return $this->write($request, function () use ($actor, $draft, $request) {
            // Submission creates a Pending application, not an official member.
            $application = $this->workflow->submit(
                $actor,
                $draft,
                $this->revision($request)
            );

            return redirect()
                ->route('membership.applications.show', $application)
                ->with(
                    'success',
                    'Application submitted for representative review.'
                );
        });
    }

    private function revision(Request $request): int
    {
        // The service rejects stale revisions to prevent accidental overwrites.
        return (int) $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
        ])['revision'];
    }

    private function write(Request $request, \Closure $operation)
    {
        // Return JSON inside the modal, or a redirect for normal forms.
        return $this->memberPanelWrite($request, $operation);
    }
}