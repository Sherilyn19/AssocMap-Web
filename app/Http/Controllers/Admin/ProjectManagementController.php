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
use App\Services\SessionUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

// This controller handles HTTP input and feedback; ProjectManagementService owns
// write rules, transactions, and audits. Form Requests validate before write actions run.
final class ProjectManagementController extends Controller
{
    public function __construct(
        private readonly ProjectManagementService $projectManagementService
    ) {
    }

    public function index(Request $request): View
    {

        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'in:active,archived'],
            'status_id' => ['nullable', 'integer', 'min:1'],
            'program_component_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:updated,title,date,budget_high,budget_low'],
            'summary' => ['nullable', 'in:total,planned,ongoing,completed,archived'],
            'page' => ['nullable', 'integer', 'min:1'],
            'summary_page' => ['nullable', 'integer', 'min:1'],
        ]);
        // Normalize optional query values once; the service expects all five keys.
        // The archived boolean fallback keeps older bookmarked URLs working.
        $filters = [
            'scope' => $request->input('scope') ?: ($request->boolean('archived') ? 'archived' : 'active'),
            'search' => trim((string) $request->input('search', '')),
            'status_id' => (string) $request->input('status_id', ''),
            'program_component_id' => (string) $request->input('program_component_id', ''),
            'sort' => $request->input('sort') ?: 'updated',
        ];
        $formData = $this->projectManagementService->formData();
        $projects = $this->projectManagementService->filteredProjects($filters)->paginate(10)->appends($filters);
        // Two paginators share this page. summary_page must stay separate from page
        // so browsing a card preserves the underlying list position and filters.
        $summaryKey = $request->input('summary');
        $summaryProjects = $summaryKey
            ? $this->projectManagementService->summaryProjects($summaryKey)->paginate(10, ['*'], 'summary_page')
                ->appends(array_merge($filters, ['summary' => $summaryKey, 'page' => $projects->currentPage()]))
            : null;

        return view('admin-pages.admin-project-management.index', [
            'projects' => $projects,
            'filters' => $filters,
            'projectStatuses' => $formData['projectStatuses'],
            'programComponents' => $formData['programComponents'],
            'summary' => $this->projectManagementService->summary(),
            'summaryKey' => $summaryKey,
            'summaryProjects' => $summaryProjects,
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
        $actorId = $this->actorId($request);
        try {
            $project = $this->projectManagementService->createProject(
                $request->validated(),
                $actorId
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
        $actorId = $this->actorId($request);
        try {
            $this->projectManagementService->updateProject(
                $project,
                $request->validated(),
                $actorId
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
        $actorId = $this->actorId($request);
        try {
            $this->projectManagementService->archiveProject(
                $project,
                $actorId
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
    // Callers resolve identity before try/catch so authentication failures are not
    // converted into ordinary save errors. Never take the audit actor from form input.
    private function actorId(Request $request): int
    {
        return (int) app(SessionUserResolver::class)->resolve($request)->id;
    }

    public function storeMaterial(
        StoreProjectMaterialRequest $request,
        Project $project
    ): RedirectResponse {
        $actorId = $this->actorId($request);
        try {
            $this->projectManagementService->addMaterial(
                $project,
                $request->validated(),
                $actorId
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
        $actorId = $this->actorId($request);
        // Separate route bindings do not prove ownership. Reject mismatched IDs
        // before the broad save-error handler so this remains a 404 response.
        abort_unless((int) $material->project_id === (int) $project->id, 404);
        try {
            $this->projectManagementService->updateMaterial(
                $project,
                $material,
                $request->validated(),
                $actorId
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
