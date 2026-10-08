<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\User;
use App\Support\GisRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GisManagementService
{
    public function importRows(array $rows, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use ($rows, $actorId): int {
            $actor = User::with('role')->findOrFail($actorId);
            abort_unless($actor->is_active && $actor->role?->role_name === 'System Administrator', 403);
            abort_if(count($rows) < 1 || count($rows) > 1000, 422);
            $ids = array_unique(array_column($rows, 'association_id'));
            sort($ids, SORT_NUMERIC);
            foreach ($ids as $id) {
                $this->lockAssociation((int) $id, $actorId);
            }
            // One bounded insert avoids a network round trip for every imported point.
            $input = array_map(fn ($row) => $this->fields($row) + ['association_id' => (int) $row['association_id']], $rows);
            $created = DB::select(<<<'SQL'
                INSERT INTO gis_locations (association_id, location_name, latitude, longitude, is_published, created_at, updated_at)
                SELECT association_id, location_name, latitude, longitude, false, clock_timestamp(), clock_timestamp()
                FROM jsonb_to_recordset(?::jsonb) AS input(association_id bigint, location_name text, latitude numeric, longitude numeric)
                RETURNING id, association_id, location_name
                SQL, [json_encode($input, JSON_THROW_ON_ERROR)]);
            $lookup = [];
            foreach ($created as $location) {
                $lookup[$location->association_id.'|'.$location->location_name] = $location->id;
            }
            $receipts = $audits = [];
            foreach ($rows as $index => $row) {
                $id = $lookup[$row['association_id'].'|'.trim($row['location_name'])];
                $receipts[] = ['user_id' => $actorId, 'token' => $row['submission_token'], 'location_id' => $id,
                    'payload_hash' => hash('sha256', json_encode($input[$index], JSON_THROW_ON_ERROR))];
                $audits[] = ['user_id' => $actorId, 'action_type' => 'CREATE', 'module' => 'GIS', 'record_id' => $id,
                    'details' => json_encode(['before' => null, 'after' => $input[$index], 'source' => 'file_import'], JSON_THROW_ON_ERROR), 'performed_at' => now()];
            }
            DB::table('gis_submissions')->insert($receipts);
            DB::table('audit_logs')->insert($audits);

            return count($created);
        });
    }

    public function create(array $data, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use ($data, $actorId): int {
            $this->authorize($actorId);
            $this->lockAssociation((int) $data['association_id'], $actorId);
            $values = $this->fields($data);
            $values['project_id'] = $this->projectId($data['project_id'] ?? null, (int) $data['association_id']);
            $hash = hash('sha256', json_encode($values + ['association_id' => (int) $data['association_id']], JSON_THROW_ON_ERROR));
            // A competing copy waits here until the first transaction commits or rolls back.
            DB::table('gis_submissions')->insertOrIgnore([
                'user_id' => $actorId, 'token' => $data['submission_token'], 'payload_hash' => $hash,
            ]);
            $receipt = DB::table('gis_submissions')->where('user_id', $actorId)
                ->where('token', $data['submission_token'])->lockForUpdate()->first();
            abort_unless($receipt && hash_equals($receipt->payload_hash, $hash), 409,
                'This submission changed. Reload GIS Mapping before trying again.');
            if ($receipt->location_id !== null) {
                return (int) $receipt->location_id;
            }
            // Only these fields may be written. The existing database trigger fills geom.
            $id = DB::table('gis_locations')->insertGetId($values + [
                'association_id' => (int) $data['association_id'],
                'is_published' => false,
                'created_at' => DB::raw('clock_timestamp()'),
                'updated_at' => DB::raw('clock_timestamp()'),
            ]);
            $this->audit($actorId, 'CREATE', $id, null, $values + ['association_id' => (int) $data['association_id']]);
            DB::table('gis_submissions')->where('id', $receipt->id)->update(['location_id' => $id]);

            return (int) $id;
        });
    }

    public function publication(
        int $id,
        string $revision,
        bool $published,
        int $actorId
    ): int {
        return app(AssociationDatabase::class)->run(function () use (
            $id,
            $revision,
            $published,
            $actorId
        ): int {
            $this->authorize($actorId);

            $parentId = DB::table('gis_locations')
                ->where('id', $id)
                ->value('association_id');

            abort_if($parentId === null, 404);

            // Lock and recheck the current assignment before touching the location.
            $this->lockAssociation((int) $parentId, $actorId);

            $location = DB::table('gis_locations')
                ->select('gis_locations.*')
                ->selectRaw(GisRevision::SQL)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_if(!$location, 404);
            abort_if((int) $location->association_id !== (int) $parentId, 409);
            abort_if(
                $location->archived_at !== null,
                409,
                'Archived locations retain read-only history.'
            );

            if ($published) {
                // Run this before the repeated-action check.
                // An inactive association must never receive a successful publish.
                $active = Association::query()
                    ->whereKey($parentId)
                    ->where('is_archived', false)
                    ->whereHas(
                        'status',
                        fn ($query) => $query->where('status_name', 'Active')
                    )
                    ->exists();

                if (!$active) {
                    throw ValidationException::withMessages([
                        'association_id' =>
                            'Only an Active, non-archived association can publish a location.',
                    ]);
                }

                $valid = is_string($location->location_name)
                    && trim($location->location_name) !== '';

                foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
                    $value = $location->$field;

                    $valid = $valid
                        && is_numeric($value)
                        && is_finite((float) $value)
                        && abs((float) $value) <= $limit;
                }

                if (!$valid) {
                    throw ValidationException::withMessages([
                        'publication' =>
                            'Correct the location name and coordinates before publishing.',
                    ]);
                }
            }

            // Repeating an explicit state does not toggle it or create another audit.
            if ((bool) $location->is_published === $published) {
                return $id;
            }

            abort_unless(
                hash_equals($location->revision, $revision),
                409,
                'This location changed. Reload GIS Mapping before continuing.'
            );

            DB::table('gis_locations')->where('id', $id)->update([
                'is_published' => $published,
                'updated_at' => DB::raw('clock_timestamp()'),
            ]);

            $this->audit(
                $actorId,
                $published ? 'PUBLISH' : 'UNPUBLISH',
                $id,
                ['is_published' => (bool) $location->is_published],
                ['is_published' => $published]
            );

            return $id;
        });
    }

    public function update(int $id, array $data, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use (
            $id,
            $data,
            $actorId
        ): int {
            $this->authorize($actorId);

            $parentId = DB::table('gis_locations')
                ->where('id', $id)
                ->value('association_id');

            abort_if($parentId === null, 404);

            // Use the same parent-first lock order as publication and archival.
            $this->lockAssociation((int) $parentId, $actorId);

            $location = DB::table('gis_locations')
                ->select('gis_locations.*')
                ->selectRaw(GisRevision::SQL)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_if(!$location, 404);
            abort_if($location->archived_at !== null, 409, 'Archived locations cannot be edited.');

            abort_if(
                (int) $location->association_id !== (int) $parentId
                || !hash_equals($location->revision, $data['revision']),
                409,
                'This location changed. Reload GIS Mapping before editing again.'
            );

            $values = $this->fields($data);

            // An omitted project field preserves its existing link.
            $projectId = array_key_exists('project_id', $data)
                ? $data['project_id']
                : $location->project_id;

            $values['project_id'] = $this->projectId(
                $projectId,
                (int) $parentId,
                $location->project_id
            );

            $before = [
                'location_name' => $location->location_name,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'project_id' => $location->project_id,
                'is_published' => (bool) $location->is_published,
            ];

            $unpublish = (bool) $location->is_published
                && $this->publicFieldsChanged($location, $values);

            $values['is_published'] = $unpublish
                ? false
                : (bool) $location->is_published;

            DB::table('gis_locations')->where('id', $id)->update([
                ...$values,
                'updated_at' => DB::raw('clock_timestamp()'),
            ]);

            $this->audit($actorId, 'UPDATE', $id, $before, $values);

            if ($unpublish) {
                // Both the edit and automatic unpublication share this transaction.
                $this->audit(
                    $actorId,
                    'UNPUBLISH',
                    $id,
                    ['is_published' => true],
                    [
                        'is_published' => false,
                        'reason' => 'Public-facing location information changed.',
                    ]
                );
            }

            return $id;
        });
    }

    private function publicFieldsChanged(object $location, array $values): bool
    {
        if (
            $location->location_name !== $values['location_name']
            || (string) $location->project_id !== (string) $values['project_id']
        ) {
            return true;
        }

        // Compare PostgreSQL numerics exactly.
        // Formatting 10.0 as 10.00 is not a coordinate change.
        $comparison = DB::selectOne(
            'SELECT (
                CAST(? AS numeric) IS DISTINCT FROM CAST(? AS numeric)
                OR CAST(? AS numeric) IS DISTINCT FROM CAST(? AS numeric)
            ) AS changed',
            [
                $location->latitude,
                $values['latitude'],
                $location->longitude,
                $values['longitude'],
            ]
        );

        return (bool) $comparison->changed;
    }

    private function authorize(int $actorId): void
    {
        // Hold the account before locking associations. Deactivation and role changes
        // must commit before this check or wait until the GIS transaction finishes.
        $actor = User::with('role')->sharedLock()->find($actorId);
        abort_unless($actor && $actor->is_active
            && in_array($actor->role?->role_name, ['System Administrator', 'Field Officer'], true),
            403, 'You do not have permission to save GIS locations.');
    }

    public function archive(int $id, string $revision, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use ($id, $revision, $actorId): int {
            $this->authorize($actorId);
            $parentId = DB::table('gis_locations')->where('id', $id)->value('association_id');
            abort_if($parentId === null, 404);
            $this->lockAssociation((int) $parentId, $actorId);
            $location = DB::table('gis_locations')->select('gis_locations.*')->selectRaw(GisRevision::SQL)
                ->where('id', $id)->lockForUpdate()->first();
            abort_if(! $location, 404);
            abort_if((int) $location->association_id !== (int) $parentId, 409);
            if ($location->archived_at !== null) {
                return $id;
            }
            abort_unless(hash_equals($location->revision, $revision), 409);
            $archivedAt = now();
            DB::table('gis_locations')->where('id', $id)->update([
                'archived_at' => $archivedAt, 'is_published' => false, 'updated_at' => DB::raw('clock_timestamp()'),
            ]);
            $this->audit($actorId, 'ARCHIVE', $id, ['archived_at' => null, 'is_published' => (bool) $location->is_published],
                ['archived_at' => $archivedAt->toIso8601String(), 'is_published' => false]);

            return $id;
        });
    }

    private function projectId(mixed $id, int $associationId, mixed $existingId = null): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        // A project archive and GIS assignment must not pass each other unchecked.
        $project = DB::table('projects')->where('id', (int) $id)->lockForUpdate()->first();
        if (! $project || (int) $project->association_id !== $associationId
            || ($project->is_archived && (int) $existingId !== (int) $id)) {
            throw ValidationException::withMessages(['project_id' => 'Choose an active project from this association, or leave the location unlinked.']);
        }

        return (int) $id;
    }

    private function lockAssociation(int $id, int $actorId): void
    {
        $association = Association::query()->lockForUpdate()->find($id);
        $actor = User::with('role')->findOrFail($actorId);
        abort_if($actor->role->role_name === 'Field Officer' && (! $association || (int) $association->field_officer_id !== $actorId), 404);
        if (! $association || $association->is_archived) {
            throw ValidationException::withMessages(['association_id' => 'This association is unavailable or archived. Reload GIS Mapping.']);
        }
    }

    private function fields(array $data): array
    {
        return ['location_name' => trim($data['location_name']), 'latitude' => (string) $data['latitude'], 'longitude' => (string) $data['longitude']];
    }

    private function audit(int $actorId, string $action, int $id, ?array $before, array $after): void
    {
        // This insert shares the location transaction. A failed audit cancels the save.
        DB::table('audit_logs')->insert([
            'user_id' => $actorId, 'action_type' => $action, 'module' => 'GIS', 'record_id' => $id,
            'details' => json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR),
            'performed_at' => now(),
        ]);
    }
}
