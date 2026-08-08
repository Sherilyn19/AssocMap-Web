<?php

// app/Services/MemberManagementService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemberManagementService
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const ASSOCIATION_ROLES = [
        'President',
        'Secretary',
        'Treasurer',
        'Member',
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Member::query()
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,field_officer_id,representative_member_id,is_archived',
                'association.areaUnit:id,name',
                'association.subUnit:id,area_unit_id,name',
                'sex:id,sex_name',
                'application:id,association_id,status_id,reviewed_by_member_id,reviewed_at,created_at',
                'application.status:id,status_name',
                'application.reviewer:id,first_name,middle_name,last_name',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = "%{$search}%";

            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->whereRaw(
                        "CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) ILIKE ?",
                        [$term]
                    )
                    ->orWhereHas(
                        'association',
                        fn (Builder $association) => $association->where('name', 'ilike', $term)
                    );
            });
        }

        $this->applyIntegerFilter($query, 'association_id', $filters['association_id'] ?? null);
        $this->applyIntegerFilter($query, 'sex_id', $filters['sex_id'] ?? null);

        if (filter_var($filters['area_unit_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $areaUnitId = (int) $filters['area_unit_id'];
            $query->whereHas(
                'association',
                fn (Builder $association) => $association->where('area_unit_id', $areaUnitId)
            );
        }

        if (filter_var($filters['sub_unit_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $subUnitId = (int) $filters['sub_unit_id'];
            $query->whereHas(
                'association',
                fn (Builder $association) => $association->where('sub_unit_id', $subUnitId)
            );
        }

        $role = trim((string) ($filters['role_in_assoc'] ?? ''));
        if ($role !== '' && in_array($role, self::ASSOCIATION_ROLES, true)) {
            $query->where('role_in_assoc', $role);
        }

        $beneficiaryType = trim((string) ($filters['beneficiary_type'] ?? ''));
        if ($beneficiaryType !== '') {
            $query->where('beneficiary_type', $beneficiaryType);
        }

        match ((string) ($filters['record_state'] ?? 'current')) {
            'archived' => $query->where('is_archived', true),
            'all' => null,
            default => $query->where('is_archived', false),
        };

        if ($this->validDate($filters['registered_from'] ?? null)) {
            $query->whereDate('date_registered', '>=', (string) $filters['registered_from']);
        }

        if ($this->validDate($filters['registered_to'] ?? null)) {
            $query->whereDate('date_registered', '<=', (string) $filters['registered_to']);
        }

        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $query->orderByDesc('last_name')->orderByDesc('first_name'),
            'registered_desc' => $query->orderByDesc('date_registered')->orderBy('last_name'),
            'registered_asc' => $query->orderBy('date_registered')->orderBy('last_name'),
            'association_asc' => $query
                ->orderBy(
                    Association::query()
                        ->select('name')
                        ->whereColumn('associations.id', 'members.association_id')
                )
                ->orderBy('last_name'),
            default => $query->orderBy('last_name')->orderBy('first_name'),
        };

        $perPage = (int) ($filters['per_page'] ?? 15);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{
     *     total:int,
     *     current:int,
     *     archived:int,
     *     representatives:int,
     *     associations_with_members:int
     * }
     */
    public function summary(): array
    {
        $memberCounts = DB::table('members')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_archived = FALSE) AS current')
            ->selectRaw('COUNT(*) FILTER (WHERE is_archived = TRUE) AS archived')
            ->selectRaw('COUNT(DISTINCT association_id) AS associations_with_members')
            ->first();

        $representatives = DB::table('associations')
            ->join('members', 'members.id', '=', 'associations.representative_member_id')
            ->where('members.is_archived', false)
            ->count();

        return [
            'total' => (int) ($memberCounts->total ?? 0),
            'current' => (int) ($memberCounts->current ?? 0),
            'archived' => (int) ($memberCounts->archived ?? 0),
            'representatives' => (int) $representatives,
            'associations_with_members' => (int) ($memberCounts->associations_with_members ?? 0),
        ];
    }

    /**
     * Compact aggregate/detail data for clickable KPI modals.
     *
     * No complete member dataset is sent to the browser.
     *
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        return [
            'sex_distribution' => DB::table('members')
                ->join('sex', 'sex.id', '=', 'members.sex_id')
                ->select('sex.sex_name')
                ->selectRaw('COUNT(*) AS member_count')
                ->groupBy('sex.sex_name')
                ->orderByDesc('member_count')
                ->get(),

            'beneficiary_distribution' => DB::table('members')
                ->selectRaw("COALESCE(NULLIF(BTRIM(beneficiary_type), ''), 'Unspecified') AS beneficiary_type")
                ->selectRaw('COUNT(*) AS member_count')
                ->groupByRaw("COALESCE(NULLIF(BTRIM(beneficiary_type), ''), 'Unspecified')")
                ->orderByDesc('member_count')
                ->limit(8)
                ->get(),

            'role_distribution' => DB::table('members')
                ->where('is_archived', false)
                ->selectRaw("COALESCE(NULLIF(BTRIM(role_in_assoc), ''), 'Unassigned') AS role_name")
                ->selectRaw('COUNT(*) AS member_count')
                ->groupByRaw("COALESCE(NULLIF(BTRIM(role_in_assoc), ''), 'Unassigned')")
                ->orderByDesc('member_count')
                ->get(),

            'members_by_association' => DB::table('associations')
                ->join('members', 'members.association_id', '=', 'associations.id')
                ->leftJoin('area_units', 'area_units.id', '=', 'associations.area_unit_id')
                ->leftJoin('sub_units', 'sub_units.id', '=', 'associations.sub_unit_id')
                ->select([
                    'associations.id',
                    'associations.name',
                    'area_units.name as municipality_name',
                    'sub_units.name as barangay_name',
                ])
                ->selectRaw('COUNT(members.id) AS total_count')
                ->selectRaw('COUNT(members.id) FILTER (WHERE members.is_archived = FALSE) AS current_count')
                ->selectRaw('COUNT(members.id) FILTER (WHERE members.is_archived = TRUE) AS archived_count')
                ->groupBy(
                    'associations.id',
                    'associations.name',
                    'area_units.name',
                    'sub_units.name'
                )
                ->orderByDesc('total_count')
                ->orderBy('associations.name')
                ->limit(10)
                ->get(),

            'recent_registrations' => DB::table('members')
                ->join('associations', 'associations.id', '=', 'members.association_id')
                ->where('members.is_archived', false)
                ->orderByDesc('members.date_registered')
                ->orderByDesc('members.id')
                ->limit(6)
                ->get([
                    'members.id',
                    'members.first_name',
                    'members.middle_name',
                    'members.last_name',
                    'members.role_in_assoc',
                    'members.date_registered',
                    'associations.name as association_name',
                ]),

            'representatives' => DB::table('associations')
                ->join('members', 'members.id', '=', 'associations.representative_member_id')
                ->leftJoin('area_units', 'area_units.id', '=', 'associations.area_unit_id')
                ->leftJoin('sub_units', 'sub_units.id', '=', 'associations.sub_unit_id')
                ->where('members.is_archived', false)
                ->orderBy('associations.name')
                ->limit(10)
                ->get([
                    'members.id as member_id',
                    'members.first_name',
                    'members.middle_name',
                    'members.last_name',
                    'members.role_in_assoc',
                    'members.contact_number',
                    'associations.name as association_name',
                    'area_units.name as municipality_name',
                    'sub_units.name as barangay_name',
                ]),
        ];
    }

    /**
     * @return array<string, Collection<int, object>|array<int, string>|array<int, int>>
     */
    public function filterOptions(): array
    {
        return [
            'associations' => DB::table('associations')
                ->orderBy('name')
                ->get(['id', 'name', 'area_unit_id', 'sub_unit_id', 'is_archived']),

            'municipalities' => DB::table('area_units')
                ->orderBy('name')
                ->get(['id', 'name', 'is_archived']),

            'barangays' => DB::table('sub_units')
                ->orderBy('name')
                ->get(['id', 'area_unit_id', 'name', 'is_archived']),

            'sexOptions' => DB::table('sex')
                ->orderBy('sex_name')
                ->get(['id', 'sex_name']),

            'roleOptions' => self::ASSOCIATION_ROLES,

            'beneficiaryTypes' => DB::table('members')
                ->whereNotNull('beneficiary_type')
                ->whereRaw("BTRIM(beneficiary_type) <> ''")
                ->distinct()
                ->orderBy('beneficiary_type')
                ->pluck('beneficiary_type'),

            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ];
    }

    public function findDetailed(Member $member): Member
    {
        return $member->load([
            'association:id,name,area_unit_id,sub_unit_id,field_officer_id,representative_member_id,is_archived',
            'association.areaUnit:id,name',
            'association.subUnit:id,area_unit_id,name',
            'sex:id,sex_name',
            'application:id,association_id,status_id,reviewed_by_member_id,reviewed_at,rejection_reason,created_at',
            'application.status:id,status_name',
            'application.reviewer:id,first_name,middle_name,last_name',
            'user:id,name,email,is_active',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Member $member, array $data, int $actorId): Member
    {
        return DB::transaction(function () use ($member, $data, $actorId): Member {
            /** @var Member $locked */
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($locked->is_archived) {
                throw new RuntimeException(
                    'Archived members are historical records and cannot be edited.'
                );
            }

            $previousRole = $locked->role_in_assoc;

            $locked->forceFill([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'birthday' => $data['birthday'],
                'sex_id' => (int) $data['sex_id'],
                'role_in_assoc' => $data['role_in_assoc'] ?? null,
                'beneficiary_type' => $data['beneficiary_type'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'address' => $data['address'] ?? null,
                'date_registered' => $data['date_registered'],
            ]);
            $locked->save();

            $changedFields = array_values(array_filter(
                array_keys($locked->getChanges()),
                fn (string $field): bool => $field !== 'updated_at'
            ));

            if ($changedFields !== []) {
                $this->writeAudit(
                    $actorId,
                    'UPDATE',
                    $locked->id,
                    'Updated member fields: '.implode(', ', $changedFields).'.'
                );
            }

            if ($previousRole !== $locked->role_in_assoc) {
                $this->writeAudit(
                    $actorId,
                    'ROLE_CHANGE',
                    $locked->id,
                    'Changed association role from '
                        .($previousRole ?: 'unassigned')
                        .' to '
                        .($locked->role_in_assoc ?: 'unassigned')
                        .'.'
                );
            }

            return $locked->fresh();
        }, 3);
    }

    public function archive(Member $member, int $actorId): Member
    {
        return DB::transaction(function () use ($member, $actorId): Member {
            /** @var Member $locked */
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($locked->is_archived) {
                return $locked;
            }

            $isRepresentative = DB::table('associations')
                ->where('representative_member_id', $locked->id)
                ->lockForUpdate()
                ->exists();

            if ($isRepresentative) {
                throw new RuntimeException(
                    'This member is currently the Association Representative. '
                    .'Assign a different representative before archiving this member.'
                );
            }

            $locked->forceFill(['is_archived' => true])->save();

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $locked->id,
                'Archived member record. The record remains available for history and reporting.'
            );

            return $locked->fresh();
        }, 3);
    }

    private function applyIntegerFilter(Builder $query, string $column, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            $query->where($column, (int) $value);
        }
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function writeAudit(
        int $actorId,
        string $action,
        int $memberId,
        string $details
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $action,
            'module' => 'Member',
            'record_id' => $memberId,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}