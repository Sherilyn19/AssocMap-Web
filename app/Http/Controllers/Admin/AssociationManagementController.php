<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignAssociationRepresentativeRequest;
use App\Http\Requests\Admin\AssociationIndexRequest;
use App\Http\Requests\Admin\StoreAssociationRequest;
use App\Http\Requests\Admin\UpdateAssociationRequest;
use App\Models\Association;
use App\Services\AssociationDatabase;
use App\Services\AssociationManagementService;
use App\Services\SessionUserResolver;
use App\Support\AssociationErrors;
use App\Support\AssociationFormState;
use App\Support\AssociationRequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class AssociationManagementController extends Controller
{
    public function __construct(private readonly AssociationManagementService $service, private readonly SessionUserResolver $sessionUser) {}

    public function index(AssociationIndexRequest $request): mixed
    {
        $this->authorizeAction($request, 'administer', Association::class);
        $filters = $request->validated();
        // Load the whole screen within one short read scope, not one transaction per dropdown.
        $data = app(AssociationDatabase::class)->run(fn () => [
            'associations' => $this->service->paginate($filters), 'summary' => $this->service->summary(),
            ...$this->service->formOptions(),
        ]);

        return view('admin-pages.admin-association-management.index', [
            ...$data, 'filters' => $filters, 'listState' => AssociationFormState::filters($request),
        ]);
    }

    public function show(Request $request, Association $association): mixed
    {
        $this->authorizeAction($request, 'view', $association);
        $state = AssociationFormState::filters($request);
        $data = app(AssociationDatabase::class)->run(fn () => [
            'association' => $this->service->findDetailed($association),
            'eligibleRepresentatives' => $this->service->eligibleRepresentatives($association),
        ]);

        return view('admin-pages.admin-association-management.show', [
            ...$data, 'backToListUrl' => route('admin.associations.index', $state),
            'representativeActionUrl' => route('admin.associations.representative', ['association' => $association, ...$state]),
        ]);
    }

    public function store(StoreAssociationRequest $request): mixed
    {
        $this->authorizeAction($request, 'create', Association::class);

        return $this->mutate($request, fn () => $this->service->create($request->validated(), $this->actorId($request)), 'Association created successfully.');
    }

    public function update(UpdateAssociationRequest $request, Association $association): mixed
    {
        $this->authorizeAction($request, 'update', $association);

        return $this->mutate($request, fn () => $this->service->update($association, $request->validated(), $this->actorId($request)), 'Association updated successfully.');
    }

    public function archive(Request $request, Association $association): mixed
    {
        $this->authorizeAction($request, 'archive', $association);

        return $this->mutate($request, fn () => $this->service->archive($association, $this->actorId($request)), $association->is_archived ? 'Association is already archived.' : 'Association archived and GIS locations unpublished.');
    }

    public function restore(Request $request, Association $association): mixed
    {
        $this->authorizeAction($request, 'restore', $association);

        return $this->mutate($request, fn () => $this->service->restore($association, $this->actorId($request)), $association->is_archived ? 'Association restored. GIS locations remain unpublished.' : 'Association is already current.');
    }

    public function representative(AssignAssociationRepresentativeRequest $request, Association $association): mixed
    {
        $this->authorizeAction($request, 'update', $association);
        $value = $request->validated('representative_member_id');

        return $this->mutate($request, fn () => $this->service->assignRepresentative($association, $value === null ? null : (int) $value, $this->actorId($request)), 'Representative selection saved. A newly appointed representative needs a private review passphrase provisioned from their member record.');
    }

    private function mutate(Request $request, \Closure $operation, string $message): mixed
    {
        try {
            $operation();
            // The service has committed. A later session-save timeout cannot undo this change.
            app(AssociationRequestContext::class)->mutationCompleted = true;
            $url = AssociationFormState::returnUrl($request);
            $request->session()->flash('success', $message);

            // Fetch submissions retain the existing form when an error occurs; successful ones navigate.
            return $request->expectsJson() ? response()->json(['redirect_url' => $url, 'message' => $message]) : redirect()->to($url);
        } catch (Throwable $error) {
            AssociationFormState::remember($request);
            $response = AssociationErrors::render($error, $request);
            if ($response !== null) {
                return $response;
            }
            throw $error; // Preserve framework validation/authorization and transaction rollback.
        }
    }

    private function authorizeAction(Request $request, string $ability, mixed $record): void
    {
        Gate::forUser($this->sessionUser->resolve($request))->authorize($ability, $record);
    }

    private function actorId(Request $request): int
    {
        return (int) $this->sessionUser->resolve($request)->id;
    }
}
