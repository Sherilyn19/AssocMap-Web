<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class GisPublicationImpact
{
    /**
     * Capture only association information relevant to public GIS.
     * Private member, representative, and account information is excluded.
     */
    public static function associationSnapshot(Association $association): array
    {
        return [
            'association_name' => $association->name,
            'municipality_id' => $association->area_unit_id === null
                ? null : (string) $association->area_unit_id,
            'barangay_id' => $association->sub_unit_id === null
                ? null : (string) $association->sub_unit_id,
            'program_component_id' => $association->program_component_id === null
                ? null : (string) $association->program_component_id,
            'association_status_id' => $association->status_id === null
                ? null : (string) $association->status_id,
        ];
    }

    /**
     * These project fields affect information returned by the public map.
     * Budget and internal remarks do not affect GIS publication.
     */
    public static function projectSnapshot(Project $project): array
    {
        return [
            'project_title' => $project->title,
            'commodity' => $project->commodity_type,
            'project_archived' => (bool) $project->is_archived,
        ];
    }

    public function associationChanged(
        int $associationId,
        array $before,
        array $after,
        int $actorId
    ): void {
        $this->unpublish(
            DB::table('gis_locations')
                ->where('association_id', $associationId),
            $before,
            $after,
            $actorId,
            'Association information or operational status changed.',
            'association',
            $associationId
        );
    }

    public function projectChanged(
        int $projectId,
        array $before,
        array $after,
        int $actorId
    ): void {
        $this->unpublish(
            DB::table('gis_locations')->where('project_id', $projectId),
            $before,
            $after,
            $actorId,
            'Linked project information changed.',
            'project',
            $projectId
        );
    }

    /**
     * The caller owns authorization and the parent-record lock.
     * The original change, unpublication, and audits must commit together.
     */
    private function unpublish(
        Builder $locations,
        array $before,
        array $after,
        int $actorId,
        string $reason,
        string $source,
        int $sourceId
    ): void {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException(
                'GIS publication changes require an existing transaction.'
            );
        }

        $previous = [];
        $current = [];

        // Store changed values only, making the history easier to understand.
        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;

            if ($old !== $value) {
                $previous[$field] = $old;
                $current[$field] = $value;
            }
        }

        if ($current === []) {
            return;
        }

        // Archived locations are already unpublished by the database constraint.
        // Filtering by the publication flag also leaves unpublished records untouched.
        $ids = $locations
            ->where('is_published', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $details = json_encode([
            'before' => ['is_published' => true, ...$previous],
            'after' => ['is_published' => false, ...$current],
            'reason' => $reason,
            'source' => $source,
            'source_id' => $sourceId,
        ], JSON_THROW_ON_ERROR);

        $performedAt = now();

        // Bound each statement without separating the transaction.
        foreach ($ids->chunk(200) as $batch) {
            DB::table('gis_locations')
                ->whereIn('id', $batch->all())
                ->update([
                    'is_published' => false,

                    // Changing the timestamp invalidates previously opened GIS revisions.
                    'updated_at' => DB::raw('clock_timestamp()'),
                ]);

            $audits = $batch->map(
                fn ($id): array => [
                    'user_id' => $actorId,
                    'action_type' => 'UNPUBLISH',
                    'module' => 'GIS',
                    'record_id' => $id,
                    'details' => $details,
                    'performed_at' => $performedAt,
                ]
            )->all();

            // Do not swallow audit failures. The caller must roll everything back.
            DB::table('audit_logs')->insert($audits);
        }
    }
}