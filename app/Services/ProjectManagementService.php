<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\ProgramComponent;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Application-layer rules for Admin Project Management.
 *
 * The database remains the final integrity boundary. This service adds
 * business validation, transaction safety, and the existing audit-log pattern.
 */
final class ProjectManagementService
{
    public function updateDelivery(\App\Models\User $actor, int $projectId, int $materialId, array $data): void
    {
        DB::transaction(function () use ($actor, $projectId, $materialId, $data): void {
            $access = app(FieldOfficerUserAccess::class);
            $project = $access->scope(Project::query(), $actor)->findOrFail($projectId);
            $association = $access->lockAssociation($actor, (int) $project->association_id);
            $project = $association->projects()->lockForUpdate()->findOrFail($projectId);
            abort_if($project->is_archived, 403, 'Archived projects cannot be changed.');
            $material = $project->materials()->lockForUpdate()->findOrFail($materialId);
            // Direct requests must not change archived material records.
            abort_if(
                $material->archived_at !== null,
                403,
                'Archived materials cannot be changed.'
            );
            $data = validator($data, ['delivery_date' => ['present', 'nullable', 'date_format:Y-m-d']])->validate();
            $before = $material->delivery_date?->toDateString();
            $material->update(['delivery_date' => $data['delivery_date']]);
            DB::table('audit_logs')->insert([
                'user_id' => $actor->id, 'action_type' => 'UPDATE', 'module' => 'Project Materials',
                'record_id' => $material->id, 'performed_at' => now(),
                'details' => json_encode(['project_id' => $project->id, 'delivery_date_before' => $before, 'delivery_date_after' => $data['delivery_date']], JSON_THROW_ON_ERROR),
            ]);
        }, 3);
    }

    /**
     * Project statuses allowed by the corrected Capstone 2 business rules.
     */
    private const PROJECT_STATUSES = [
        'Planned',
        'Ongoing',
        'Completed',
    ];

    /**
     * Material statuses confirmed by the audited status dataset.
     */
    // This existing vocabulary mixes condition and delivery labels. Keep it intact
    // until a separate business-rule/schema change defines their replacement.
    private const MATERIAL_STATUSES = [
        'Pending',
        'Good',
        'Damaged',
        'For Repair',
        'Delivered',
    ];


    /** Record scope is exclusive: false = active, true = archived. */
    public function listProjects(bool $archived = false): Builder
    {
        return $this->projectRecords()->where('is_archived', $archived);
    }

    // Fetch display labels in the record query to avoid three remote round trips
    // per table. Scalar lookups preserve projects whose related record is missing.
    public function projectRecords(): Builder
    {
        return Project::query()
            ->select('projects.*')
            ->addSelect([
                'association_name' => Association::query()->select('name')->whereColumn('associations.id', 'projects.association_id'),
                'program_component_name' => ProgramComponent::query()->select('name')->whereColumn('program_components.id', 'projects.program_component_id'),
                'project_status_name' => Status::query()->select('status_name')->whereColumn('statuses.id', 'projects.status_id'),
            ])
            ->withCount('materials');
    }

    public function filteredProjects(array $filters): Builder
    {
        $query = $this->listProjects($filters['scope'] === 'archived');
        if ($filters['status_id'] !== '') {
            $query->where('status_id', (int) $filters['status_id']);
        }
        if ($filters['program_component_id'] !== '') {
            $query->where('program_component_id', (int) $filters['program_component_id']);
        }
        if ($filters['search'] !== '') {
            $search = '%' . $filters['search'] . '%';
            // Group the OR search clauses so they cannot bypass the archive/status filters.
            // ILIKE is PostgreSQL case-insensitive matching; Eloquent binds the search value.
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('title', 'ilike', $search)
                    ->orWhere('commodity_type', 'ilike', $search)
                    ->orWhereHas('association', fn (Builder $association) => $association->where('name', 'ilike', $search));
            });
        }

        // Only this fixed map can supply SQL column names and directions.
        [$column, $direction] = match ($filters['sort']) {
            'title' => ['title', 'asc'],
            'date' => ['implementation_date', 'desc'],
            default => ['updated_at', 'desc'],
        };
        // Missing dates belong last. The ID breaks ties for stable pagination.
        return $query->orderByRaw($column . ' ' . $direction . ' NULLS LAST')->orderByDesc('id');
    }

    /** Summary cards describe the whole register, independently of filters. */
    public function summary(): array
    {
        $summary = ['total' => 0, 'active' => 0, 'archived' => 0, 'planned' => 0, 'ongoing' => 0, 'completed' => 0, 'other' => 0];
        // Keep archived records in the total but out of the active status cards.
        // The left join and "other" bucket retain records with an unfamiliar/missing status.
        $groups = Project::query()->leftJoin('statuses', 'projects.status_id', '=', 'statuses.id')
            ->select('projects.is_archived', 'statuses.status_name')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('projects.is_archived', 'statuses.status_name')->get();
        foreach ($groups as $group) {
            $count = (int) $group->aggregate;
            $summary['total'] += $count;
            if ($group->is_archived) {
                $summary['archived'] += $count;
            } else {
                $summary['active'] += $count;
                $key = strtolower((string) $group->status_name);
                $summary[in_array($key, ['planned', 'ongoing', 'completed'], true) ? $key : 'other'] += $count;
            }
        }
        return $summary;
    }

    // Match the card definitions in summary(): total spans both archive scopes;
    // status groups contain active records only, regardless of main-list filters.
    public function summaryProjects(string $summary): Builder
    {
        $query = $this->projectRecords();
        if ($summary === 'archived') {
            $query->where('is_archived', true);
        } elseif (in_array($summary, ['planned', 'ongoing', 'completed'], true)) {
            $query->where('is_archived', false)
                ->whereHas('status', fn (Builder $status) => $status->where('status_name', ucfirst($summary)));
        }
        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    public function filterData(): array
    {
        return [
            'programComponents' => ProgramComponent::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'projectStatuses' => Status::query()
                ->whereIn('status_name', self::PROJECT_STATUSES)
                ->orderBy('status_name')
                ->get(['id', 'status_name']),
        ];
    }

    public function materialStatuses(): \Illuminate\Database\Eloquent\Collection
    {
        return Status::query()
            ->whereIn('status_name', self::MATERIAL_STATUSES)
            ->orderBy('status_name')
            ->get(['id', 'status_name']);
    }

    public function formData(): array
    {
        return array_merge($this->filterData(), [
            'associations' => Association::query()
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'name']),
            'materialStatuses' => $this->materialStatuses(),
        ]);
    }

    // Each write and its audit entry share a transaction: either both commit or both roll back.
    // The second transaction argument (3) allows retries for concurrency failures.
    public function createProject(array $data, int $actorId): Project
    {
        return DB::transaction(function () use ($data, $actorId): Project {
            $this->validateProjectReferences($data);

            $project = Project::create([
                'association_id' => (int) $data['association_id'],
                'title' => trim((string) $data['title']),
                'commodity_type' => trim((string) $data['commodity_type']),
                'program_component_id' => (int) $data['program_component_id'],
                'implementation_date' => $data['implementation_date'],
                // Save a supplied budget; preserve zero as a valid recorded amount.
                ...(array_key_exists('budget', $data)
                    ? ['budget' => $data['budget']]
                    : []),
                'terminated_on' => $data['terminated_on'] ?? null,
                'status_id' => (int) $data['status_id'],
                'remarks' => $data['remarks'] ?? null,
                'is_archived' => false,
            ]);

            $this->writeAudit(
                $actorId,
                'CREATE',
                $project->id,
                'Created project: ' . $project->title
            );

            return $project->load(['association', 'programComponent', 'status']);
        }, 3);
    }

        public function updateProject(
        Project $project,
        array $data,
        int $actorId
    ): Project {
        return DB::transaction(function () use (
            $project,
            $data,
            $actorId
        ): Project {
            // Lock associations before the project to coordinate with GIS writes.
            // Re-read the project instead of comparing against a stale route model.
            $project = $this->lockProjectForGisWrite(
                $project,
                (int) $data['association_id']
            );

            $this->ensureProjectIsWritable($project);
            $this->validateProjectReferences($data);

            if (
                (int) $data['association_id'] !== (int) $project->association_id
                && DB::table('gis_locations')
                    ->where('project_id', $project->id)
                    ->exists()
            ) {
                throw new \InvalidArgumentException(
                    'Projects linked to active or archived GIS locations must remain in the same association.'
                );
            }

            $gisImpact = app(GisPublicationImpact::class);
            $beforeGis = $gisImpact->projectSnapshot($project);

            $project->update([
                'association_id' => (int) $data['association_id'],
                'title' => trim((string) $data['title']),
                'commodity_type' => trim((string) $data['commodity_type']),
                'program_component_id' => (int) $data['program_component_id'],
                'implementation_date' => $data['implementation_date'],

                // Omitted budget preserves the existing value; explicit null clears it.
                ...(array_key_exists('budget', $data)
                    ? ['budget' => $data['budget']]
                    : []),

                'terminated_on' => $data['terminated_on'] ?? null,
                'status_id' => (int) $data['status_id'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            // Private project changes, such as remarks or budget, do not unpublish.
            $gisImpact->projectChanged(
                (int) $project->id,
                $beforeGis,
                $gisImpact->projectSnapshot($project),
                $actorId
            );

            $this->writeAudit(
                $actorId,
                'UPDATE',
                $project->id,
                'Updated project: '.$project->title
                    .'; status ID: '.$project->status_id
                    .'; termination date: '
                    .($project->terminated_on?->toDateString() ?? 'Not recorded')
            );

            return $project->fresh(['association', 'programComponent', 'status']);
        }, 3);
    }

    public function archiveProject(Project $project, int $actorId): void
    {
        DB::transaction(function () use ($project, $actorId): void {
            $project = $this->lockProjectForGisWrite($project);

            // Repeated archival must not create duplicate audit events.
            if ($project->is_archived) {
                return;
            }

            $gisImpact = app(GisPublicationImpact::class);
            $beforeGis = $gisImpact->projectSnapshot($project);

            $project->update(['is_archived' => true]);

            // The GIS location itself is retained.
            // Its changed public presentation requires a new publication decision.
            $gisImpact->projectChanged(
                (int) $project->id,
                $beforeGis,
                $gisImpact->projectSnapshot($project),
                $actorId
            );

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $project->id,
                'Archived project: '.$project->title
            );
        }, 3);
    }

    public function addMaterial(Project $project, array $data, int $actorId): ProjectMaterial
    {
        return DB::transaction(function () use ($project, $data, $actorId): ProjectMaterial {
            $this->ensureProjectIsWritable($project);
            $status = $this->validateMaterialStatus((int) $data['status_id']);

            $material = $project->materials()->create([
                'item_name' => trim((string) $data['item_name']),
                'quantity' => $data['quantity'],
                'unit' => trim((string) $data['unit']),
                'unit_cost' => $data['unit_cost'] ?? null,
                'status_id' => $status->id,
                'delivery_date' => $data['delivery_date'] ?? null,
            ]);

            $this->writeAudit(
                $actorId,
                'CREATE_MATERIAL',
                $project->id,
                'Added material: ' . $material->item_name
            );

            return $material->load('status');
        }, 3);
    }

    public function updateMaterial(
        Project $project,
        ProjectMaterial $material,
        array $data,
        int $actorId
    ): ProjectMaterial {
        return DB::transaction(function () use ($project, $material, $data, $actorId): ProjectMaterial {
            $this->ensureProjectIsWritable($project);

            // Read the current archive state under a lock before applying changes.
            $material = ProjectMaterial::query()
                ->lockForUpdate()
                ->findOrFail($material->id);

            if ($material->archived_at !== null) {
                throw new \InvalidArgumentException(
                    'Archived materials cannot be changed.'
                );
            }

            // Never trust a route-bound material alone. The material must belong
            // to the project currently being managed.
            if ((int) $material->project_id !== (int) $project->id) {
                abort(404);
            }

            $status = $this->validateMaterialStatus((int) $data['status_id']);

            $material->update([
                'item_name' => trim((string) $data['item_name']),
                'quantity' => $data['quantity'],
                'unit' => trim((string) $data['unit']),
                'unit_cost' => $data['unit_cost'] ?? null,
                'status_id' => $status->id,
                'delivery_date' => $data['delivery_date'] ?? null,
            ]);

            $this->writeAudit(
                $actorId,
                'UPDATE_MATERIAL',
                $project->id,
                'Updated material: ' . $material->item_name
            );

            return $material->fresh('status');
        }, 3);
    }

    // Request validation checks input shape and existence; the service also checks
    // business eligibility because it can be called outside an HTTP form submission.
    private function validateProjectReferences(array $data): void
    {
        // Validate money at the shared write boundary, including direct service calls.
        // The maximum matches the existing numeric(14,2) budget column.
        validator($data, [
            'budget' => [
                'sometimes',
                'nullable',
                'numeric',
                'decimal:0,2',
                'between:0,999999999999.99',
            ],
        ])->validate();
        $association = Association::query()
            ->whereKey((int) $data['association_id'])
            ->where('is_archived', false)
            ->first();

        if (! $association) {
            throw new \InvalidArgumentException('The selected association is not available for Project Management.');
        }

        $programComponentExists = ProgramComponent::query()
            ->whereKey((int) $data['program_component_id'])
            ->exists();

        if (! $programComponentExists) {
            throw new \InvalidArgumentException('The selected Program Component does not exist.');
        }

        $status = Status::query()
            ->whereKey((int) $data['status_id'])
            ->whereIn('status_name', self::PROJECT_STATUSES)
            ->first();

        if (! $status) {
            throw new \InvalidArgumentException('The selected project status is not allowed.');
        }

    }

    private function validateMaterialStatus(int $statusId): Status
    {
        $status = Status::query()
            ->whereKey($statusId)
            ->whereIn('status_name', self::MATERIAL_STATUSES)
            ->first();

        if (! $status) {
            throw new \InvalidArgumentException('The selected material status is not allowed.');
        }

        return $status;
    }

    /**
     * Serialize project changes with GIS publication.
     * Read the project again after locking, rather than trusting route-bound values.
     */
    private function lockProjectForGisWrite(
        Project $project,
        ?int $targetAssociationId = null
    ): Project {
        $sourceAssociationId = (int) $project->association_id;

        $associationIds = array_unique([
            $sourceAssociationId,
            $targetAssociationId ?? $sourceAssociationId,
        ]);

        sort($associationIds, SORT_NUMERIC);

        foreach ($associationIds as $associationId) {
            Association::query()
                ->whereKey($associationId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $current = Project::query()
            ->whereKey($project->id)
            ->lockForUpdate()
            ->firstOrFail();

        // A concurrent transfer requires the user to reload the project.
        abort_if(
            (int) $current->association_id !== $sourceAssociationId,
            409,
            'This project changed associations. Reload it before continuing.'
        );

        return $current;
    }

    private function ensureProjectIsWritable(Project $project): void
    {
        // Call inside a transaction. Locking the parent serializes material/project
        // writes with archiving, preventing a stale open form from editing an archived record.
        $current = Project::query()->lockForUpdate()->findOrFail($project->id);
        if ($current->is_archived) {
            throw new \InvalidArgumentException('Archived projects cannot be modified.');
        }
    }

    private function writeAudit(
        int $actorId,
        string $actionType,
        int $recordId,
        string $details
    ): void {
        // A project change must fail if its required audit entry cannot be saved.
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $actorId,
                'action_type' => $actionType,
                'module' => 'Project Management',
                'record_id' => $recordId,
                'details' => $details,
                'performed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Audit logging must not silently corrupt the project transaction.
            // The exception is logged and rethrown so the outer transaction can
            // roll back instead of leaving a partially audited write.
            Log::error('ProjectManagementService audit-log failure', [
                'action_type' => $actionType,
                'record_id' => $recordId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
