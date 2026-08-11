<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectMaterialRequest;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectMaterialRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Services\ProjectManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class ProjectManagementController extends Controller
{
    public function __construct(
        private readonly ProjectManagementService $projectManagementService
    ) {
    }

    public function index(Request $request): View
    {
        $includeArchived = $request->boolean('archived');

        $projects = $this->projectManagementService
            ->listProjects($includeArchived)
            ->when(
                $request->filled('status_id'),
                fn ($query) => $query->where('status_id', (int) $request->integer('status_id'))
            )
            ->when(
                $request->filled('program_component_id'),
                fn ($query) => $query->where('program_component_id', (int) $request->integer('program_component_id'))
            )
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    $search = trim((string) $request->input('search'));

                    $query->where(function ($nested) use ($search): void {
                        $nested
                            ->where('title', 'ilike', '%' . $search . '%')
                            ->orWhere('commodity_type', 'ilike', '%' . $search . '%')
                            ->orWhereHas(
                                'association',
                                fn ($associationQuery) => $associationQuery->where('name', 'ilike', '%' . $search . '%')
                            );
                    });
                }
            )
            ->paginate(10)
            ->withQueryString();

        $formData = $this->projectManagementService->formData();

        return view('admin-pages.admin-project-management.index', [
            'projects' => $projects,
            'projectStatuses' => $formData['projectStatuses'],
            'programComponents' => $formData['programComponents'],
            'includeArchived' => $includeArchived,
        ]);
    }

    public function create(): View
    {
        return view(
            'admin-pages.admin-project-management.create',
            $this->projectManagementService->formData()
        );
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        try {
            $project = $this->projectManagementService->createProject(
                $request->validated(),
                $this->actorId($request)
            );

            return redirect()
                ->route('projects.show', $project)
                ->with('success', 'Project created successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'The project could not be created. Please verify the entered information and try again.');
        }
    }

    public function show(Project $project): View
    {
        $project->load([
            'association',
            'programComponent',
            'status',
            'materials.status',
        ]);

        return view('admin-pages.admin-project-management.show', [
            'project' => $project,
            'materialStatuses' => $this->projectManagementService->formData()['materialStatuses'],
        ]);
    }

    public function edit(Project $project): View
    {
        if ($project->is_archived) {
            abort(404);
        }

        return view(
            'admin-pages.admin-project-management.edit',
            array_merge(
                ['project' => $project->load(['association', 'programComponent', 'status'])],
                $this->projectManagementService->formData()
            )
        );
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $this->projectManagementService->updateProject(
                $project,
                $request->validated(),
                $this->actorId($request)
            );

            return redirect()
                ->route('projects.show', $project)
                ->with('success', 'Project updated successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'The project could not be updated. Please try again.');
        }
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        try {
            $this->projectManagementService->archiveProject(
                $project,
                $this->actorId($request)
            );

            return redirect()
                ->route('projects.index')
                ->with('success', 'Project archived successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->with('error', 'The project could not be archived. Please try again.');
        }
    }

    /**
     * Resolve the authenticated administrator using the project's established
     * authentication/session conventions instead of assuming one session key.
     */
    private function actorId(Request $request): int
    {
        $actorId = auth()->id()
            ?? $request->session()->get('user_id')
            ?? $request->session()->get('authenticated_user_id');

        abort_if(!$actorId, 401, 'Authenticated user could not be identified.');

        return (int) $actorId;
    }

    public function storeMaterial(
        StoreProjectMaterialRequest $request,
        Project $project
    ): RedirectResponse {
        try {
            $this->projectManagementService->addMaterial(
                $project,
                $request->validated(),
                $this->actorId($request)
            );

            return back()->with('success', 'Project material added successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'The project material could not be added.');
        }
    }

    public function updateMaterial(
        UpdateProjectMaterialRequest $request,
        Project $project,
        ProjectMaterial $material
    ): RedirectResponse {
        try {
            $this->projectManagementService->updateMaterial(
                $project,
                $material,
                $request->validated(),
                $this->actorId($request)
            );

            return back()->with('success', 'Project material updated successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'The project material could not be updated.');
        }
    }
}