<?php

namespace App\Services;

use App\Models\AreaUnit;
use App\Models\SubUnit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AreaManagementService
 * app/Services/AreaManagementService.php
 *
 * Owns Area Management business logic for municipalities and barangays.
 * Controllers remain thin; this service handles filtering, detail retrieval,
 * archive guards, transactions, and audit logging.
 */
class AreaManagementService
{
    public function listMunicipalities(array $filters): LengthAwarePaginator
    {
        $query = AreaUnit::query()
            ->withCount([
                'subUnits as barangay_count' => fn ($q) => $q->where('is_archived', false),
                'subUnits as total_barangay_count',
            ]);

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('address', 'ilike', $term)
                    ->orWhere('province', 'ilike', $term);
            });
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_archived', false);
        } elseif (($filters['status'] ?? '') === 'archived') {
            $query->where('is_archived', true);
        }

        $sort = in_array($filters['muni_sort'] ?? '', ['name', 'created_at', 'updated_at'], true)
            ? $filters['muni_sort']
            : 'name';

        $query->orderBy($sort);

        $municipalities = $query->paginate(12, ['*'], 'muni_page')->withQueryString();

        $this->attachAssociationCounts(
            $municipalities->getCollection(),
            'area_unit_id',
            'association_count'
        );

        return $municipalities;
    }

    public function listBarangays(array $filters): LengthAwarePaginator
    {
        $query = SubUnit::query()
            ->select('sub_units.*', 'area_units.name as area_unit_name')
            ->join('area_units', 'area_units.id', '=', 'sub_units.area_unit_id');

        if (!empty($filters['brgy_search'])) {
            $term = '%' . trim($filters['brgy_search']) . '%';
            $query->where('sub_units.name', 'ilike', $term);
        }

        if (!empty($filters['area_unit_id'])) {
            $query->where('sub_units.area_unit_id', (int) $filters['area_unit_id']);
        }

        if (($filters['brgy_status'] ?? '') === 'active') {
            $query->where('sub_units.is_archived', false);
        } elseif (($filters['brgy_status'] ?? '') === 'archived') {
            $query->where('sub_units.is_archived', true);
        }

        $sort = in_array($filters['brgy_sort'] ?? '', ['name', 'created_at', 'updated_at'], true)
            ? $filters['brgy_sort']
            : 'name';

        $query->orderBy($sort === 'name' ? 'sub_units.name' : "sub_units.{$sort}");

        $barangays = $query->paginate(10, ['*'], 'brgy_page')->withQueryString();

        $this->attachAssociationCounts(
            $barangays->getCollection(),
            'sub_unit_id',
            'association_count'
        );

        return $barangays;
    }

    /**
     * Read-only detail payload for the municipality "View" modal.
     */
    public function viewMunicipality(int $id): array
    {
        $areaUnit = AreaUnit::query()
            ->with([
                'subUnits' => fn ($query) => $query
                    ->orderBy('is_archived')
                    ->orderBy('name')
                    ->limit(50),
            ])
            ->withCount([
                'subUnits as barangay_count' => fn ($q) => $q->where('is_archived', false),
                'subUnits as total_barangay_count',
            ])
            ->findOrFail($id);

        return [
            'type' => 'municipality',
            'id' => $areaUnit->id,
            'name' => $areaUnit->name,
            'province' => $areaUnit->province ?: 'Cebu',
            'address' => $areaUnit->address ?: 'No address on file',
            'is_archived' => (bool) $areaUnit->is_archived,
            'status' => $areaUnit->is_archived ? 'Archived' : 'Active',
            'barangay_count' => (int) $areaUnit->barangay_count,
            'total_barangay_count' => (int) $areaUnit->total_barangay_count,
            'association_count' => $this->activeAssociationCount('area_unit_id', $areaUnit->id),
            'created_at' => optional($areaUnit->created_at)->format('d M Y, h:i A'),
            'updated_at' => optional($areaUnit->updated_at)->format('d M Y, h:i A'),
            'barangays' => $areaUnit->subUnits->map(fn (SubUnit $subUnit) => [
                'id' => $subUnit->id,
                'name' => $subUnit->name,
                'status' => $subUnit->is_archived ? 'Archived' : 'Active',
                'is_archived' => (bool) $subUnit->is_archived,
            ])->values(),
        ];
    }

    /**
     * Read-only detail payload for the barangay "View" modal.
     */
    public function viewBarangay(int $id): array
    {
        $subUnit = SubUnit::query()
            ->with('areaUnit')
            ->findOrFail($id);

        return [
            'type' => 'barangay',
            'id' => $subUnit->id,
            'name' => $subUnit->name,
            'municipality' => $subUnit->areaUnit?->name ?? 'Unknown municipality',
            'area_unit_id' => $subUnit->area_unit_id,
            'province' => $subUnit->areaUnit?->province ?: 'Cebu',
            'municipality_status' => $subUnit->areaUnit?->is_archived ? 'Archived' : 'Active',
            'is_archived' => (bool) $subUnit->is_archived,
            'status' => $subUnit->is_archived ? 'Archived' : 'Active',
            'association_count' => $this->activeAssociationCount('sub_unit_id', $subUnit->id),
            'created_at' => optional($subUnit->created_at)->format('d M Y, h:i A'),
            'updated_at' => optional($subUnit->updated_at)->format('d M Y, h:i A'),
        ];
    }

    public function summaryCounts(): array
    {
        return [
            'total_municipalities'    => AreaUnit::count(),
            'active_municipalities'   => AreaUnit::where('is_archived', false)->count(),
            'archived_municipalities' => AreaUnit::where('is_archived', true)->count(),
            'total_barangays'         => SubUnit::count(),
            'active_barangays'        => SubUnit::where('is_archived', false)->count(),
            'archived_barangays'      => SubUnit::where('is_archived', true)->count(),
            'total_associations'      => $this->totalActiveAssociations(),
            'coverage_label'          => 'South & North Cebu',
        ];
    }

    public function activeMunicipalitiesForDropdown()
    {
        return AreaUnit::where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function createMunicipality(array $data, ?int $actorId): AreaUnit
    {
        return DB::transaction(function () use ($data, $actorId) {
            $areaUnit = AreaUnit::create([
                'name'        => trim($data['name']),
                'province'    => 'Cebu',
                'address'     => $data['address'] ?? null,
                'is_archived' => false,
            ]);

            $this->logAction($actorId, 'CREATE', $areaUnit->id, "Created municipality {$areaUnit->name}");

            return $areaUnit;
        });
    }

    public function updateMunicipality(int $id, array $data, ?int $actorId): AreaUnit
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $areaUnit = AreaUnit::query()->lockForUpdate()->findOrFail($id);

            $areaUnit->update([
                'name'    => trim($data['name']),
                'address' => $data['address'] ?? null,
            ]);

            $this->logAction($actorId, 'UPDATE', $areaUnit->id, "Updated municipality {$areaUnit->name}");

            return $areaUnit;
        });
    }

    /**
     * Toggle archive/restore. Blocks archiving while active barangays
     * or active associations still reference this municipality.
     *
     * @return array{ok: bool, is_archived?: bool, message?: string}
     */
    public function toggleArchiveMunicipality(int $id, ?int $actorId): array
    {
        return DB::transaction(function () use ($id, $actorId) {
            $areaUnit = AreaUnit::query()->lockForUpdate()->findOrFail($id);

            if (!$areaUnit->is_archived) {
                $activeBarangays = SubUnit::where('area_unit_id', $id)
                    ->where('is_archived', false)
                    ->count();

                if ($activeBarangays > 0) {
                    return [
                        'ok' => false,
                        'message' => "Cannot archive - {$activeBarangays} active barangay(s) still belong to this municipality.",
                    ];
                }

                $activeAssociations = $this->activeAssociationCount('area_unit_id', $id);
                if ($activeAssociations > 0) {
                    return [
                        'ok' => false,
                        'message' => "Cannot archive - {$activeAssociations} association(s) are still registered under this municipality.",
                    ];
                }
            }

            $areaUnit->is_archived = !$areaUnit->is_archived;
            $areaUnit->save();

            $action = $areaUnit->is_archived ? 'ARCHIVE' : 'RESTORE';
            $this->logAction($actorId, $action, $areaUnit->id, "{$action} municipality {$areaUnit->name}");

            return ['ok' => true, 'is_archived' => (bool) $areaUnit->is_archived];
        });
    }

    public function createBarangay(array $data, ?int $actorId): SubUnit
    {
        return DB::transaction(function () use ($data, $actorId) {
            $subUnit = SubUnit::create([
                'area_unit_id' => (int) $data['area_unit_id'],
                'name'         => trim($data['name']),
                'is_archived'  => false,
            ]);

            $this->logAction($actorId, 'CREATE', $subUnit->id, "Created barangay {$subUnit->name}");

            return $subUnit;
        });
    }

    public function updateBarangay(int $id, array $data, ?int $actorId): SubUnit
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $subUnit = SubUnit::query()->lockForUpdate()->findOrFail($id);

            $subUnit->update([
                'area_unit_id' => (int) $data['area_unit_id'],
                'name'         => trim($data['name']),
            ]);

            $this->logAction($actorId, 'UPDATE', $subUnit->id, "Updated barangay {$subUnit->name}");

            return $subUnit;
        });
    }

    /**
     * Toggle archive/restore. Blocks archiving while active
     * associations still reference this barangay.
     *
     * @return array{ok: bool, is_archived?: bool, message?: string}
     */
    public function toggleArchiveBarangay(int $id, ?int $actorId): array
    {
        return DB::transaction(function () use ($id, $actorId) {
            $subUnit = SubUnit::query()->lockForUpdate()->findOrFail($id);

            if (!$subUnit->is_archived) {
                $activeAssociations = $this->activeAssociationCount('sub_unit_id', $id);

                if ($activeAssociations > 0) {
                    return [
                        'ok' => false,
                        'message' => "Cannot archive - {$activeAssociations} association(s) are still registered under this barangay.",
                    ];
                }
            }

            $subUnit->is_archived = !$subUnit->is_archived;
            $subUnit->save();

            $action = $subUnit->is_archived ? 'ARCHIVE' : 'RESTORE';
            $this->logAction($actorId, $action, $subUnit->id, "{$action} barangay {$subUnit->name}");

            return ['ok' => true, 'is_archived' => (bool) $subUnit->is_archived];
        });
    }

    private function attachAssociationCounts(Collection $records, string $foreignKey, string $attribute): void
    {
        if ($records->isEmpty() || !Schema::hasTable('associations')) {
            $records->each(fn ($record) => $record->setAttribute($attribute, 0));
            return;
        }

        $ids = $records->pluck('id')->filter()->values();

        $counts = DB::table('associations')
            ->select($foreignKey, DB::raw('COUNT(*) as aggregate_count'))
            ->whereIn($foreignKey, $ids)
            ->where('is_archived', false)
            ->groupBy($foreignKey)
            ->pluck('aggregate_count', $foreignKey);

        $records->each(function ($record) use ($counts, $attribute) {
            $record->setAttribute($attribute, (int) ($counts[$record->id] ?? 0));
        });
    }

    private function totalActiveAssociations(): int
    {
        if (!Schema::hasTable('associations')) {
            return 0;
        }

        return DB::table('associations')
            ->where('is_archived', false)
            ->count();
    }

    /**
     * Counts active associations referencing a given column/value.
     * Returns 0 while the associations module has not been built yet.
     */
    private function activeAssociationCount(string $column, int $value): int
    {
        if (!Schema::hasTable('associations')) {
            return 0;
        }

        return DB::table('associations')
            ->where($column, $value)
            ->where('is_archived', false)
            ->count();
    }

    private function logAction(?int $actorId, string $actionType, int|string $recordId, string $details): void
    {
        if (!$actorId || !Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id'      => $actorId,
            'action_type'  => $actionType,
            'module'       => 'Area',
            'record_id'    => $recordId,
            'details'      => $details,
            'performed_at' => now(),
        ]);
    }
}
