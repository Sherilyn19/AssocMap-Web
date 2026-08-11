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
    private const MATERIAL_STATUSES = [
        'Pending',
        'Good',
        'Damaged',
        'For Repair',
        'Delivered',
    ];

    public function listProjects(bool $includeArchived = false): Builder
    {
        return Project::query()
            ->with(['association', 'programComponent', 'status'])
            ->withCount('materials')
            ->when(
                ! $includeArchived,
                fn (Builder $query) => $query->where('is_archived', false)
            )
            ->latest('id');
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
        if ($project->is_archived) {
            throw new \InvalidArgumentException('Archived projects cannot be modified.');
        }
    }

    private function writeAudit(
        int $actorId,
        string $actionType,
        int $recordId,
        string $details
    ): void {
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