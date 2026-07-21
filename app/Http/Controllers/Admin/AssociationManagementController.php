<?php

// app/Http/Controllers/Admin/AssociationManagementController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignAssociationRepresentativeRequest;
use App\Http\Requests\Admin\StoreAssociationRequest;
use App\Http\Requests\Admin\UpdateAssociationRequest;
use App\Models\Association;
use App\Services\AssociationManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class AssociationManagementController extends Controller
{
    private const LIST_STATE_KEYS = [
        'search',
        'area_unit_id',
        'sub_unit_id',
        'program_component_id',
        'field_officer_id',
        'status_id',
        'archive_state',
        'sort',
        'page',
    ];

    public function __construct(
        private readonly AssociationManagementService $service
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'area_unit_id',
            'sub_unit_id',
            'program_component_id',
            'field_officer_id',
            'status_id',
            'archive_state',
            'sort',
        ]);

        return view('admin-pages.admin-association-management.index', [
            'associations' => $this->service->paginate($filters),
            'summary' => $this->service->summary(),
            'filters' => $filters,
            'listState' => $this->listState($request),
            ...$this->service->formOptions(),
        ]);
    }

    public function store(StoreAssociationRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $this->actorId($request));

        return redirect()
            ->route('admin.associations.index')
            ->with('success', 'Association created successfully.');
    }

    public function show(Request $request, Association $association): View
    {
        $listState = $this->listState($request);

        return view('admin-pages.admin-association-management.show', [
            'association' => $this->service->findDetailed($association),
            'eligibleRepresentatives' => $this->service->eligibleRepresentatives($association),
            'backToListUrl' => route('admin.associations.index', $listState),
            'representativeActionUrl' => route('admin.associations.representative', [
                'association' => $association,
                ...$listState,
            ]),
        ]);
    }

    public function update(
        UpdateAssociationRequest $request,
        Association $association
    ): RedirectResponse {
        try {
            $updated = $this->service->update(
                $association,
                $request->validated(),
                $this->actorId($request)
            );

            $message = $association->field_officer_id !== $updated->field_officer_id
                ? 'Association updated and Field Officer reassigned successfully.'
                : 'Association updated successfully.';

            return redirect()
                ->route('admin.associations.index')
                ->with('success', $message);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function archive(Request $request, Association $association): RedirectResponse
    {
        try {
            $this->service->archive($association, $this->actorId($request));

            return back()->with('success', 'Association archived successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The association could not be archived. Please try again.');
        }
    }

    public function restore(Request $request, Association $association): RedirectResponse
    {
        try {
            $this->service->restore($association, $this->actorId($request));

            return back()->with('success', 'Association restored successfully.');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function representative(
        AssignAssociationRepresentativeRequest $request,
        Association $association
    ): RedirectResponse {
        $listState = $this->listState($request);
        $showUrl = $this->associationShowUrl($association, $listState);

        try {
            $this->service->assignRepresentative(
                $association,
                $request->validated('representative_member_id'),
                $this->actorId($request)
            );

            return redirect($showUrl)
                ->with('success', 'Association Representative updated successfully.');
        } catch (RuntimeException $exception) {
            return redirect($showUrl)
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect($showUrl)
                ->withInput()
                ->with('error', 'The Association Representative could not be updated. Please try again.');
        }
    }

    /**
     * Keep only scalar, non-empty list filters that are safe to carry between routes.
     *
     * @return array<string, string>
     */

    private function listState(Request $request): array
    {
        $state = [];

        foreach (self::LIST_STATE_KEYS as $key) {
            $value = $request->query($key);

            if (!is_scalar($value) || $value === '') {
                continue;
            }

            $state[$key] = (string) $value;
        }

        if (isset($state['page']) && (!ctype_digit($state['page']) || (int) $state['page'] < 1)) {
            unset($state['page']);
        }

        return $state;
    }

    /**
     * @param array<string, string> $listState
     */
    
    private function associationShowUrl(Association $association, array $listState): string
    {
        return route('admin.associations.show', [
            'association' => $association,
            ...$listState,
        ]);
    }

    private function actorId(Request $request): int
    {
        $actorId = auth()->id()
            ?? $request->session()->get('user_id')
            ?? $request->session()->get('authenticated_user_id');

        abort_if(!$actorId, 401, 'Authenticated user could not be identified.');

        return (int) $actorId;
    }
}