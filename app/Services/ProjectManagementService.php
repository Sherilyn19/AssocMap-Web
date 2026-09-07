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

    // Load related labels together to avoid extra queries for each displayed row.
    // withCount supplies the material count without loading every material record.
    public function projectRecords(): Builder
    {
        return Project::query()
            ->with(['association', 'programComponent', 'status'])
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
            'budget_high' => ['budget', 'desc'],
            'budget_low' => ['budget', 'asc'],
            default => ['updated_at', 'desc'],
        };
        // Missing dates/budgets belong last. The ID breaks ties for stable pagination.
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

    public function formData(): array
    {
        return [
            'associations' => Association::query()
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'name']),
            'programComponents' => ProgramComponent::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'projectStatuses' => Status::query()
                ->whereIn('status_name', self::PROJECT_STATUSES)
                ->orderBy('status_name')
                ->get(['id', 'status_name']),
            'materialStatuses' => Status::query()
                ->whereIn('status_name', self::MATERIAL_STATUSES)
                ->orderBy('status_name')
                ->get(['id', 'status_name']),
        ];
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
                'budget' => $data['budget'],
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

    public function updateProject(Project $project, array $data, int $actorId): Project
    {
        return DB::transaction(function () use ($project, $data, $actorId): Project {
            $this->ensureProjectIsWritable($project);
            $this->validateProjectReferences($data);

            $project->update([
                'association_id' => (int) $data['association_id'],
                'title' => trim((string) $data['title']),
                'commodity_type' => trim((string) $data['commodity_type']),
                'program_component_id' => (int) $data['program_component_id'],
                'implementation_date' => $data['implementation_date'],
                'budget' => $data['budget'],
                'status_id' => (int) $data['status_id'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->writeAudit(
                $actorId,
                'UPDATE',
                $project->id,
                'Updated project: ' . $project->title
            );

            return $project->fresh(['association', 'programComponent', 'status']);
        }, 3);
    }

    public function archiveProject(Project $project, int $actorId): void
    {
        DB::transaction(function () use ($project, $actorId): void {
            // Re-read under a row lock: another request may have changed the record
            // since route binding. Repeated archive requests must not add duplicate audits.
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($project->is_archived) {
                return;
            }

            $project->update([
                'is_archived' => true,
            ]);

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $project->id,
                'Archived project: ' . $project->title
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

        if ((float) $data['budget'] < 0) {
            throw new \InvalidArgumentException('Project budget cannot be negative.');
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
        // Preserve compatibility with installations without the audit table. If it
        // exists, an insert failure is rethrown below and rolls back the business write.
        if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
            return;
        }

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
