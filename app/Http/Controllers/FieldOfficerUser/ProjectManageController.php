<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Services\FieldOfficerUserAccess;
use App\Services\ProjectManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ProjectManageController extends Controller
{
    public function __construct(
        private readonly SessionUserResolver $resolver,
        private readonly FieldOfficerUserAccess $access,
        private readonly ProjectManagementService $service,
    ) {
    }

    public function form(Request $request)
    {
        $actor = $this->resolver->resolve($request);

        abort_unless(
            $actor->is_active
            && $actor->role?->role_name === 'Field Officer',
            403
        );

        // The route defines the form type. A submitted input cannot change it.
        $kind = (string) $request->route('kind');
        $projectId = $request->route('project');
        $materialId = $request->route('material');

        $project = $projectId
            ? $this->access->scope(
                Project::with('association'),
                $actor
            )->findOrFail($projectId)
            : null;

        if ($project) {
            abort_if(
                $project->is_archived || $project->association->is_archived,
                403,
                'Archived projects and associations cannot be changed.'
            );
        }

        $material = $materialId
            ? $project->materials()->findOrFail($materialId)
            : null;

        abort_if(
            $material && $material->archived_at !== null,
            403,
            'Archived materials cannot be changed.'
        );

        // Reuse existing allowed status lists without exposing global associations.
        $options = $this->service->filterData();
        $options['materialStatuses'] = $this->service->materialStatuses();
        $options['associations'] = $this->access->associations($actor)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view(
            'field-officer-user.projects.manage',
            compact('kind', 'project', 'material', 'options')
        );
    }

    public function save(Request $request)
    {
        $actor = $this->resolver->resolve($request);
        $kind = (string) $request->route('kind');
        $projectId = $request->route('project');
        $materialId = $request->route('material');

        // Resolve the project through current assignment before accepting a write.
        $existing = $projectId
            ? $this->access->scope(Project::query(), $actor)
                ->findOrFail($projectId)
            : null;

        $associationId = $existing
            ? (int) $existing->association_id
            : (int) $request->validate([
                'association_id' => ['required', 'integer', 'min:1'],
            ])['association_id'];

        try {
            DB::transaction(function () use (
                $request,
                $actor,
                $kind,
                $existing,
                $associationId,
                $materialId
            ): void {
                // Recheck the active officer and assignment inside the transaction.
                // Holding the association lock prevents reassignment during this write.
                $association = $this->access->lockAssociation(
                    $actor,
                    $associationId
                );

                $project = $existing
                    ? $association->projects()
                        ->lockForUpdate()
                        ->findOrFail($existing->id)
                    : null;

                abort_if(
                    $project && $project->is_archived,
                    403,
                    'Archived projects cannot be changed.'
                );

                if ($kind === 'project') {
                    $data = $request->validate([
                        // Existing projects cannot be transferred through this form.
                        'association_id' => $project
                            ? ['prohibited']
                            : ['required', 'integer', 'min:1'],
                        'title' => ['required', 'string', 'max:255'],
                        'commodity_type' => ['required', 'string', 'max:255'],
                        'program_component_id' => [
                            'required', 'integer', 'exists:program_components,id',
                        ],
                        'implementation_date' => ['required', 'date_format:Y-m-d'],
                        // Retain this optional input; the shared service validates its amount.
                        'budget' => ['sometimes'],
                        'terminated_on' => [
                            'nullable',
                            'date_format:Y-m-d',
                            'after_or_equal:implementation_date',
                            'before_or_equal:today',
                        ],
                        'status_id' => ['required', 'integer', 'exists:statuses,id'],
                        'remarks' => ['nullable', 'string', 'max:5000'],
                        'is_archived' => ['prohibited'],
                    ]);

                    // Ownership comes from the locked association, never edit input.
                    $data['association_id'] = $association->id;

                    if ($project) {
                        $this->service->updateProject($project, $data, $actor->id);
                    } else {
                        $this->service->createProject($data, $actor->id);
                    }

                    return;
                }

                abort_unless($project, 404);

                if ($kind === 'archive-project') {
                    $request->validate(['confirm' => ['accepted']]);
                    $this->service->archiveProject($project, $actor->id);

                    return;
                }

                // A material ID is valid only within this authorized project.
                $material = $materialId
                    ? $project->materials()
                        ->lockForUpdate()
                        ->findOrFail($materialId)
                    : null;

                abort_if(
                    $material && $material->archived_at !== null,
                    403,
                    'Archived materials cannot be changed.'
                );

                if ($kind === 'archive-material') {
                    abort_unless($material, 404);
                    $request->validate(['confirm' => ['accepted']]);

                    $material->forceFill(['archived_at' => now()])->save();

                    // Archive and audit must succeed together.
                    DB::table('audit_logs')->insert([
                        'user_id' => $actor->id,
                        'action_type' => 'ARCHIVE',
                        'module' => 'Project Materials',
                        'record_id' => $material->id,
                        'details' => 'Archived material in project #'.$project->id
                            .'. Historical records retained.',
                        'performed_at' => now(),
                    ]);

                    return;
                }

                abort_unless($kind === 'material', 404);

                $data = $request->validate([
                    'item_name' => ['required', 'string', 'max:255'],
                    'quantity' => ['required', 'numeric', 'gt:0'],
                    'unit' => ['required', 'string', 'max:100'],
                    'unit_cost' => ['nullable', 'numeric', 'min:0'],
                    'status_id' => ['required', 'integer', 'exists:statuses,id'],
                    'delivery_date' => ['nullable', 'date_format:Y-m-d'],
                    'project_id' => ['prohibited'],
                    'association_id' => ['prohibited'],
                    'archived_at' => ['prohibited'],
                ]);

                if ($material) {
                    $this->service->updateMaterial(
                        $project,
                        $material,
                        $data,
                        $actor->id
                    );
                } else {
                    $this->service->addMaterial($project, $data, $actor->id);
                }
            }, 3);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Changes saved.']);
            }

            return redirect()->route('officer.projects.index')
                ->with('success', 'Changes saved.');
        } catch (InvalidArgumentException $exception) {
            // Existing project services use this exception for business-rule errors.
            return $this->failure($request, $exception->getMessage(), 422);
        } catch (QueryException $exception) {
            report($exception);

            // Never expose SQL, connection details, or database credentials.
            return $this->failure(
                $request,
                'The change could not be confirmed. Reload the record before retrying.',
                503
            );
        }

        // Laravel handles validation, authorization, and missing-record exceptions.
        // Do not turn a denied request into a misleading success response.
    }

    private function failure(Request $request, string $message, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->withInput()->with('error', $message);
    }
}