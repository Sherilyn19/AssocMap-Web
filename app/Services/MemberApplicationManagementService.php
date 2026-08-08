<?php

// app/Services/MemberApplicationManagementService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\MemberApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MemberApplicationManagementService
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MemberApplication::query()
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,representative_member_id,is_archived',
                'association.areaUnit:id,name',
                'association.subUnit:id,area_unit_id,name',
                'association.representative:id,association_id,first_name,middle_name,last_name,role_in_assoc,is_archived',
                'sex:id,sex_name',
                'status:id,status_name',
                'reviewer:id,association_id,first_name,middle_name,last_name',
                'member:id,association_id,application_id,first_name,middle_name,last_name,is_archived',
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

        if (filter_var($filters['association_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $query->where('association_id', (int) $filters['association_id']);
        }

        if (filter_var($filters['status_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $statusId = (int) $filters['status_id'];

            $allowedStatus = DB::table('statuses')
                ->where('id', $statusId)
                ->whereIn('status_name', ['Pending', 'Approved', 'Rejected'])
                ->exists();

            if ($allowedStatus) {
                $query->where('status_id', $statusId);
            }
        }

        if ($this->validDate($filters['submitted_from'] ?? null)) {
            $query->whereDate('created_at', '>=', (string) $filters['submitted_from']);
        }

        if ($this->validDate($filters['submitted_to'] ?? null)) {
            $query->whereDate('created_at', '<=', (string) $filters['submitted_to']);
        }

        match ((string) ($filters['sort'] ?? 'submitted_desc')) {
            'name_asc' => $query->orderBy('last_name')->orderBy('first_name'),
            'name_desc' => $query->orderByDesc('last_name')->orderByDesc('first_name'),
            'submitted_asc' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = (int) ($filters['per_page'] ?? 15);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{total:int,pending:int,approved:int,rejected:int}
     */
    public function summary(): array
    {
        $row = DB::table('member_applications')
            ->join('statuses', 'statuses.id', '=', 'member_applications.status_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE statuses.status_name = 'Pending') AS pending")
            ->selectRaw("COUNT(*) FILTER (WHERE statuses.status_name = 'Approved') AS approved")
            ->selectRaw("COUNT(*) FILTER (WHERE statuses.status_name = 'Rejected') AS rejected")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
        ];
    }

    /**
     * @return array<string, Collection<int, object>|array<int, int>>
     */
    public function filterOptions(): array
    {
        return [
            'associations' => DB::table('associations')
                ->orderBy('name')
                ->get(['id', 'name', 'is_archived']),

            'applicationStatuses' => DB::table('statuses')
                ->whereIn('status_name', ['Pending', 'Approved', 'Rejected'])
                ->orderByRaw(
                    "CASE status_name WHEN 'Pending' THEN 1 WHEN 'Approved' THEN 2 ELSE 3 END"
                )
                ->get(['id', 'status_name']),

            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ];
    }

    public function findDetailed(MemberApplication $application): MemberApplication
    {
        return $application->load([
            'association:id,name,area_unit_id,sub_unit_id,representative_member_id,is_archived',
            'association.areaUnit:id,name',
            'association.subUnit:id,area_unit_id,name',
            'association.representative:id,association_id,first_name,middle_name,last_name,role_in_assoc,is_archived',
            'sex:id,sex_name',
            'status:id,status_name',
            'reviewer:id,association_id,first_name,middle_name,last_name',
            'member:id,association_id,application_id,first_name,middle_name,last_name,is_archived',
        ]);
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}