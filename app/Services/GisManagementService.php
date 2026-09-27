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
    public function create(array $data, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use ($data, $actorId): int {
            $this->authorize($actorId);
            $values = $this->fields($data);
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
            $this->lockAssociation((int) $data['association_id']);
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

    public function publication(int $id, string $revision, bool $published, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use ($id, $revision, $published, $actorId): int {
            $this->authorize($actorId);
            $parentId = DB::table('gis_locations')->where('id', $id)->value('association_id');
            abort_if($parentId === null, 404);
            $this->lockAssociation((int) $parentId);
            $location = DB::table('gis_locations')->select('gis_locations.*')->selectRaw(GisRevision::SQL)
                ->where('id', $id)->lockForUpdate()->first();
            abort_if(! $location, 404);
            abort_if((int) $location->association_id !== (int) $parentId, 409);
            // Repeating the same explicit state is a no-op, never a toggle or a second audit.
            if ((bool) $location->is_published === $published) {
                return $id;
            }
            abort_unless(hash_equals($location->revision, $revision), 409);
            if ($published) {
                $valid = is_string($location->location_name) && trim($location->location_name) !== '';
                foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
                    $value = $location->$field;
                    $valid = $valid && is_numeric($value) && is_finite((float) $value) && abs((float) $value) <= $limit;
                }
                if (! $valid) {
                    throw ValidationException::withMessages(['publication' => 'Correct the location name and coordinates before publishing.']);
                }
            }
            DB::table('gis_locations')->where('id', $id)->update(['is_published' => $published, 'updated_at' => DB::raw('clock_timestamp()')]);
            $this->audit($actorId, $published ? 'PUBLISH' : 'UNPUBLISH', $id,
                ['is_published' => (bool) $location->is_published], ['is_published' => $published]);

            return $id;
        });
    }

    public function update(int $id, array $data, int $actorId): int
    {
        return app(AssociationDatabase::class)->run(function () use ($id, $data, $actorId): int {
            $this->authorize($actorId);
            $parentId = DB::table('gis_locations')->where('id', $id)->value('association_id');
            abort_if($parentId === null, 404, 'This location is no longer available. Reload GIS Mapping.');
            // Match association archival: lock the parent first, then the location.
            $this->lockAssociation((int) $parentId);
            $location = DB::table('gis_locations')->select(['id', 'association_id', 'location_name', 'latitude', 'longitude'])
                ->selectRaw(GisRevision::SQL)->where('id', $id)->lockForUpdate()->first();
            abort_if(! $location, 404, 'This location is no longer available. Reload GIS Mapping.');
            abort_if((int) $location->association_id !== (int) $parentId || ! hash_equals($location->revision, $data['revision']),
                409, 'This location changed after you opened the form. Reload GIS Mapping before editing again.');
            $before = ['location_name' => $location->location_name, 'latitude' => $location->latitude, 'longitude' => $location->longitude];
            $values = $this->fields($data);
            // Do not write association_id or is_published: preserve their latest saved values.
            DB::table('gis_locations')->where('id', $id)->update($values + ['updated_at' => DB::raw('clock_timestamp()')]);
            $this->audit($actorId, 'UPDATE', $id, $before, $values);

            return $id;
        });
    }

    private function authorize(int $actorId): void
    {
        $allowed = User::query()->whereKey($actorId)->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('role_name', 'System Administrator'))->exists();
        abort_unless($allowed, 403, 'You do not have permission to save GIS locations.');
    }

    private function lockAssociation(int $id): void
    {
        $association = Association::query()->lockForUpdate()->find($id);
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
