<?php

// app/Services/AssociationManagementService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AssociationManagementService
{
    /**
     * Return the administrator list with constrained eager loading and calculated counts.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Association::query()
            ->with([
                'areaUnit:id,name',
                'subUnit:id,area_unit_id,name',
                'programComponent:id,name',
                'fieldOfficer:id,name,email',
                'representative:id,association_id,first_name,middle_name,last_name,role_in_assoc',
                'status:id,status_name',
            ])
            ->withCount([
                'members as members_count' => fn (Builder $query) => $query->where('is_archived', false),
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        $this->applyIntegerFilter($query, 'area_unit_id', $filters['area_unit_id'] ?? null);
        $this->applyIntegerFilter($query, 'sub_unit_id', $filters['sub_unit_id'] ?? null);
        $this->applyIntegerFilter($query, 'program_component_id', $filters['program_component_id'] ?? null);
        $this->applyIntegerFilter($query, 'field_officer_id', $filters['field_officer_id'] ?? null);
        $this->applyIntegerFilter($query, 'status_id', $filters['status_id'] ?? null);

        match ((string) ($filters['archive_state'] ?? 'current')) {
            'archived' => $query->where('is_archived', true),
            'all' => null,
            default => $query->where('is_archived', false),
        };

        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $query->orderByDesc('name'),
            'date_joined_desc' => $query->orderByDesc('date_joined')->orderBy('name'),
            'date_joined_asc' => $query->orderBy('date_joined')->orderBy('name'),
            'created_desc' => $query->orderByDesc('created_at'),
            'updated_desc' => $query->orderByDesc('updated_at'),
            default => $query->orderBy('name'),
        };

        return $query->paginate(10)->withQueryString();
    }

    /**
     * @return array{total:int, active:int, inactive:int, archived:int}
     */
    public function summary(): array
    {
        $rows = DB::table('associations')
            ->leftJoin('statuses', 'statuses.id', '=', 'associations.status_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE associations.is_archived = FALSE AND statuses.status_name = 'Active') AS active")
            ->selectRaw("COUNT(*) FILTER (WHERE associations.is_archived = FALSE AND statuses.status_name = 'Inactive') AS inactive")
            ->selectRaw('COUNT(*) FILTER (WHERE associations.is_archived = TRUE) AS archived')
            ->first();

        return [
            'total' => (int) ($rows->total ?? 0),
            'active' => (int) ($rows->active ?? 0),
            'inactive' => (int) ($rows->inactive ?? 0),
            'archived' => (int) ($rows->archived ?? 0),
        ];
    }

    /**
     * @return array<string, Collection<int, object>>
     */
    public function formOptions(): array
    {
        return [
            'municipalities' => DB::table('area_units')
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'name']),

            'barangays' => DB::table('sub_units')
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'area_unit_id', 'name']),

            'programComponents' => DB::table('program_components')
                ->orderBy('name')
                ->get(['id', 'name']),

            'fieldOfficers' => DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.role_name', 'Field Officer')
                ->where('users.is_active', true)
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email']),

            'associationStatuses' => DB::table('statuses')
                ->whereIn('status_name', ['Active', 'Inactive'])
                ->orderByRaw("CASE WHEN status_name = 'Active' THEN 1 ELSE 2 END")
                ->get(['id', 'status_name']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $actorId): Association
    {
        return DB::transaction(function () use ($data, $actorId): Association {
            $association = Association::query()->create([
                ...$data,
                'representative_member_id' => null,
                'is_archived' => false,
            ]);

            $this->writeAudit(
                $actorId,
                'CREATE',
                $association->id,
                "Created association '{$association->name}'."
            );

            return $association->fresh();
        }, 3);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Association $association, array $data, int $actorId): Association
    {
        return DB::transaction(function () use ($association, $data, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                throw new RuntimeException('Archived associations must be restored before editing.');
            }

            $beforeOfficer = $locked->field_officer_id;
            $beforeRepresentative = $locked->representative_member_id;
            $beforeStatus = $locked->status_id;

            $locked->fill($data);
            $locked->save();

            $changes = array_keys($locked->getChanges());
            $this->writeAudit(
                $actorId,
                'UPDATE',
                $locked->id,
                'Updated association master information: '.implode(', ', $changes).'.'
            );

            if ($beforeOfficer !== $locked->field_officer_id) {
                $this->writeAudit(
                    $actorId,
                    'ASSIGN_OFFICER',
                    $locked->id,
                    "Reassigned Field Officer from user {$beforeOfficer} to user {$locked->field_officer_id}."
                );
            }

            if ($beforeStatus !== $locked->status_id) {
                $this->writeAudit(
                    $actorId,
                    'STATUS_CHANGE',
                    $locked->id,
                    "Changed operational status from {$beforeStatus} to {$locked->status_id}."
                );
            }

            if ($beforeRepresentative !== $locked->representative_member_id) {
                $this->auditRepresentativeChange(
                    $actorId,
                    $locked->id,
                    $beforeRepresentative,
                    $locked->representative_member_id
                );
            }

            return $locked->fresh();
        }, 3);
    }

    public function archive(Association $association, int $actorId): Association
    {
        return DB::transaction(function () use ($association, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                return $locked;
            }

            $locked->forceFill(['is_archived' => true])->save();

            if (Schema::hasTable('gis_locations')) {
                DB::table('gis_locations')
                    ->where('association_id', $locked->id)
                    ->where('is_published', true)
                    ->update([
                        'is_published' => false,
                        'updated_at' => now(),
                    ]);
            }

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $locked->id,
                "Archived association '{$locked->name}' and unpublished its GIS locations."
            );

            return $locked->fresh();
        }, 3);
    }

    public function restore(Association $association, int $actorId): Association
    {
        return DB::transaction(function () use ($association, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()
                ->with(['areaUnit:id,is_archived', 'subUnit:id,area_unit_id,is_archived'])
                ->lockForUpdate()
                ->findOrFail($association->id);

            if (!$locked->is_archived) {
                return $locked;
            }

            if ($locked->areaUnit?->is_archived || $locked->subUnit?->is_archived) {
                throw new RuntimeException(
                    'The association cannot be restored while its municipality or barangay is archived.'
                );
            }

            if ((int) $locked->subUnit?->area_unit_id !== (int) $locked->area_unit_id) {
                throw new RuntimeException(
                    'The association cannot be restored because its barangay no longer belongs to its municipality.'
                );
            }

            if ($locked->representative_member_id !== null) {
                $validRepresentative = Member::query()
                    ->whereKey($locked->representative_member_id)
                    ->where('association_id', $locked->id)
                    ->where('is_archived', false)
                    ->exists();

                if (!$validRepresentative) {
                    $locked->representative_member_id = null;
                }
            }

            $locked->is_archived = false;
            $locked->save();

            $this->writeAudit(
                $actorId,
                'RESTORE',
                $locked->id,
                "Restored association '{$locked->name}'. GIS locations remain unpublished."
            );

            return $locked->fresh();
        }, 3);
    }

    public function assignRepresentative(
        Association $association,
        ?int $representativeMemberId,
        int $actorId
    ): Association {
        return DB::transaction(function () use (
            $association,
            $representativeMemberId,
            $actorId
        ): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                throw new RuntimeException('Restore the association before changing its representative.');
            }

            $previous = $locked->representative_member_id;
            $locked->representative_member_id = $representativeMemberId;
            $locked->save();

            $this->auditRepresentativeChange(
                $actorId,
                $locked->id,
                $previous,
                $representativeMemberId
            );

            return $locked->fresh();
        }, 3);
    }

    public function findDetailed(Association $association): Association
    {
        return $association->load([
            'areaUnit:id,name',
            'subUnit:id,name,area_unit_id',
            'programComponent:id,name',
            'fieldOfficer:id,name,email',
            'representative:id,association_id,first_name,middle_name,last_name,role_in_assoc',
            'status:id,status_name',
        ])->loadCount([
            'members as members_count' => fn (Builder $query) => $query->where('is_archived', false),
            'memberApplications as pending_applications_count' => function (Builder $query): void {
                $query->whereHas('status', fn (Builder $status) => $status->where('status_name', 'Pending'));
            },
            'projects as projects_count' => fn (Builder $query) => $query->where('is_archived', false),
            'trainings as trainings_count' => fn (Builder $query) => $query->where('is_archived', false),
            'gisLocations as gis_locations_count',
            'gisLocations as published_gis_locations_count' => fn (Builder $query) => $query->where('is_published', true),
        ]);
    }

    /**
     * @return Collection<int, Member>
     */
    public function eligibleRepresentatives(Association $association): Collection
    {
        return Member::query()
            ->where('association_id', $association->id)
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'association_id',
                'first_name',
                'middle_name',
                'last_name',
                'role_in_assoc',
            ]);
    }

    private function applyIntegerFilter(Builder $query, string $column, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            $query->where($column, (int) $value);
        }
    }

    private function auditRepresentativeChange(
        int $actorId,
        int $associationId,
        ?int $previous,
        ?int $current
    ): void {
        $action = match (true) {
            $previous === null && $current !== null => 'ASSIGN_REPRESENTATIVE',
            $previous !== null && $current === null => 'REMOVE_REPRESENTATIVE',
            default => 'CHANGE_REPRESENTATIVE',
        };

        $this->writeAudit(
            $actorId,
            $action,
            $associationId,
            "Representative changed from ".($previous ?? 'none')." to ".($current ?? 'none')."."
        );
    }

    private function writeAudit(
        int $actorId,
        string $action,
        int $associationId,
        string $details
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $action,
            'module' => 'Association',
            'record_id' => $associationId,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}