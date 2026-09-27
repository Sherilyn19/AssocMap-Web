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
            $this->lockAssociation((int) $data['association_id']);
            // Only these fields may be written. The existing database trigger fills geom.
            $values = $this->fields($data);
            $id = DB::table('gis_locations')->insertGetId($values + [
                'association_id' => (int) $data['association_id'],
                'is_published' => false,
                'created_at' => DB::raw('clock_timestamp()'),
                'updated_at' => DB::raw('clock_timestamp()'),
            ]);
            $this->audit($actorId, 'CREATE', $id, null, $values + ['association_id' => (int) $data['association_id']]);
            return (int) $id;
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
        return ['location_name' => $data['location_name'], 'latitude' => $data['latitude'], 'longitude' => $data['longitude']];
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
