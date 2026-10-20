<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\AreaUnit;
use App\Models\Association;
use App\Services\AreaRecordAccess;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

final class AreaController extends Controller
{

    public function index(
        Request $request,
        SessionUserResolver $resolver,
        AreaRecordAccess $access
    ) {
        // Both pages use the same validation and filter defaults.
        $filters = $this->coverageFilters($request);

        $actor = $resolver->resolve($request);
        abort_unless($actor, 403);

        // Apply account ownership before loading or counting records.
        $assigned = $this->filteredCoverageQuery(
            $access->associations($actor),
            $filters
        )
            ->with(['areaUnit', 'subUnit', 'status'])
            ->withCount([
                'members as current_members_count' =>
                    fn ($query) => $query->where('is_archived', false),
                'projects as retained_projects_count' =>
                    fn ($query) => $query->where('is_archived', false),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $hasIssue = fn (Association $association): bool =>
            $this->hasGeographicIssue($association);

        $search = $filters['search'];
        $archive = $filters['archive'];
        $quality = $filters['quality'];

        // The database query above already applies the authorized filters.
        $filtered = $assigned;
        $issues = $filtered->filter($hasIssue)->values();

        // Build modal records from the same authorized, filtered collection as the table.
        $analytics = [
            'municipalities' => $filtered
                ->filter(fn ($association) => $association->areaUnit)
                ->groupBy('area_unit_id')
                ->map(fn ($items) => [
                    'title' => $items->first()->areaUnit->name,
                    'detail' => $items->count().' matching association(s)',
                ])->values()->all(),

            // Use IDs for grouping so similar names do not merge different records.
            'barangays' => $filtered
                ->reject($hasIssue)
                ->groupBy('sub_unit_id')
                ->map(fn ($items) => [
                    'title' => $items->first()->subUnit->name,
                    'detail' => $items->first()->areaUnit->name
                        .' · '.$items->count().' matching association(s)',
                ])->values()->all(),

            'associations' => $filtered->map(fn ($association) => [
                'title' => $association->name,
                'detail' => implode(' · ', [
                    $association->areaUnit?->name ?? 'Municipality not recorded',
                    $association->subUnit?->name ?? 'Barangay not recorded',
                    $association->is_archived
                        ? 'Archived'
                        : ($association->status?->status_name ?? 'Status not recorded'),
                ]),
            ])->values()->all(),

            'issues' => $issues->map(function ($association): array {
                $reasons = [];

                if (! $association->areaUnit) {
                    $reasons[] = 'Municipality not recorded';
                }

                if (! $association->subUnit) {
                    $reasons[] = 'Barangay not recorded';
                } elseif ($association->areaUnit
                    && (int) $association->subUnit->area_unit_id
                        !== (int) $association->area_unit_id) {
                    $reasons[] = 'Barangay belongs to another municipality';
                }

                return [
                    'title' => $association->name,
                    'detail' => implode(' · ', $reasons),
                ];
            })->values()->all(),
        ];

        // Derive card totals from their modal records so both always agree.
        $summary = array_map(
            static fn (array $records): int => count($records),
            $analytics
        );

        // Records without a municipality remain visible in the issues list.
        $rows = $filtered
            ->filter(fn ($item) => $item->areaUnit)
            ->groupBy('area_unit_id')
            ->map(function ($items) use ($hasIssue): array {
                $area = $items->first()->areaUnit;

                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'archived' => (bool) $area->is_archived,
                    'barangays' => $items
                        ->reject($hasIssue)
                        ->unique('sub_unit_id')
                        ->pluck('subUnit.name')
                        ->sort()
                        ->values(),
                    'associations' => $items->count(),
                    'members' => $items->sum('current_members_count'),
                    'projects' => $items->sum('retained_projects_count'),
                    'issues' => $items->filter($hasIssue)->count(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $areas = $this->paginateCoverage($rows, $request, $filters);

        return view('field-officer-user.areas.index', compact(
            'areas',
            'analytics',
            'summary',
            'issues',
            'search',
            'archive',
            'quality'
        ));
    }

    public function show(
        Request $request,
        int $areaUnit,
        SessionUserResolver $resolver,
        AreaRecordAccess $access
    ) {
        $filters = $this->coverageFilters($request);

        $actor = $resolver->resolve($request);
        abort_unless($actor, 403);

        // Establish assignment access before applying user-selected filters.
        $scope = $access->associations($actor);

        $area = AreaUnit::query()
            ->whereIn('id', (clone $scope)->select('area_unit_id'))
            ->findOrFail($areaUnit);

        $query = $this->filteredCoverageQuery(
            (clone $scope)->where('associations.area_unit_id', $area->id),
            $filters
        )
            ->with(['areaUnit', 'subUnit', 'status', 'programComponent'])
            ->withCount([
                'members as current_members_count' =>
                    fn ($query) => $query->where('is_archived', false),

                'projects as retained_projects_count' =>
                    fn ($query) => $query->where('is_archived', false),

                // Preserve the existing role-specific GIS visibility rules.
                'gisLocations as mapped_locations_count' =>
                    fn ($query) => $query->when(
                        $actor->role?->role_name === 'Association Member',
                        fn ($query) => $query->publiclyVisible(),
                        fn ($query) => $query->whereNull('archived_at')
                    ),
            ])
            ->orderBy('associations.name')
            ->orderBy('associations.id');

        // Count matching records, then retrieve only the requested page.
        $total = (clone $query)->toBase()->getCountForPagination();
        $perPage = 10;

        $page = min(
            $filters['page'],
            max(1, (int) ceil($total / $perPage))
        );

        $records = $query->forPage($page, $perPage)->get();

        // Carry only validated filters into links; each view owns its page number.
        $coverageQuery = [
            'search' => trim($filters['search'] ?? ''),
            'archive' => $filters['archive'] ?? 'current',
            'status' => $filters['status'],
            'quality' => $filters['quality'] ?? 'all',
        ];

        $associations = new LengthAwarePaginator(
            $records,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $coverageQuery]
        );

        return view('field-officer-user.areas.show', compact(
            'area', 'associations', 'coverageQuery'
        ));
    }

    public function details(
        Request $request,
        int $areaUnit,
        int $association,
        string $section,
        SessionUserResolver $resolver,
        AreaRecordAccess $access
    ) {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless(
            in_array(
                $section,
                ['association', 'members', 'projects', 'gis'],
                true
            ),
            404
        );

        $actor = $resolver->resolve($request);
        abort_unless($actor, 403);

        // Recheck ownership for every drawer request, including copied URLs.
        $association = $access->associations($actor)
            ->where('area_unit_id', $areaUnit)
            ->with(['areaUnit', 'subUnit', 'programComponent', 'status'])
            ->withCount([
                'members as current_members_count' =>
                    fn ($query) => $query->where('is_archived', false),

                'projects as retained_projects_count' =>
                    fn ($query) => $query->where('is_archived', false),

                'gisLocations as mapped_locations_count' =>
                    fn ($query) => $query->when(
                        $actor->role?->role_name === 'Association Member',
                        fn ($query) => $query->publiclyVisible(),
                        fn ($query) => $query->whereNull('archived_at')
                    ),
            ])
            ->findOrFail($association);

        // Load only the requested child records and preserve their pagination.
        $records = match ($section) {
            'members' => $association->members()
                ->where('is_archived', false)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->paginate(8)
                ->withQueryString(),

            'projects' => $association->projects()
                ->where('is_archived', false)
                ->with('status')
                ->orderBy('title')
                ->orderBy('id')
                ->paginate(8)
                ->withQueryString(),

            'gis' => $association->gisLocations()
                ->when(
                    $actor->role?->role_name === 'Association Member',
                    fn ($query) => $query->publiclyVisible(),
                    fn ($query) => $query->whereNull('archived_at')
                )
                ->orderBy('location_name')
                ->orderBy('id')
                ->paginate(8)
                ->withQueryString(),

            default => null,
        };

        // Keep the existing three-location preview in the association overview.
        $locations = $section === 'association'
            ? $association->gisLocations()
                ->when(
                    $actor->role?->role_name === 'Association Member',
                    fn ($query) => $query->publiclyVisible(),
                    fn ($query) => $query->whereNull('archived_at')
                )
                ->orderBy('location_name')
                ->orderBy('id')
                ->limit(3)
                ->get()
            : collect();

        return view('field-officer-user.areas.drawer', compact(
            'association',
            'section',
            'records',
            'locations'
        ));
    }

    /**
     * Filter an already-authorized association query.
     * This method never replaces the account's access restrictions.
     */
    private function filteredCoverageQuery(
        \Illuminate\Database\Eloquent\Builder $query,
        array $filters
    ): \Illuminate\Database\Eloquent\Builder {
        if ($filters['archive'] !== 'all') {
            $query->where(
                'associations.is_archived',
                $filters['archive'] === 'archived'
            );
        }

        if ($filters['status'] !== '') {
            $query->whereHas('status', function ($status) use ($filters) {
                $status->where('status_name', $filters['status']);
            });
        }

        if ($filters['quality'] === 'incomplete') {
            $query->where(function ($geography) {
                $geography
                    ->whereDoesntHave('areaUnit')
                    ->orWhereDoesntHave('subUnit')
                    ->orWhereHas('subUnit', function ($barangay) {
                        $barangay->whereColumn(
                            'sub_units.area_unit_id',
                            '<>',
                            'associations.area_unit_id'
                        )->orWhereNull('sub_units.area_unit_id');
                    });
            });
        }

        if ($filters['search'] !== '') {
            // POSITION treats % and _ literally, matching the previous PHP search.
            $needle = $filters['search'];

            $query->whereRaw(
                "POSITION(
                    LOWER(?) IN LOWER(CONCAT_WS(' ',
                        associations.name,
                        (SELECT name FROM area_units
                        WHERE area_units.id = associations.area_unit_id),
                        (SELECT name FROM sub_units
                        WHERE sub_units.id = associations.sub_unit_id)
                    ))
                ) > 0",
                [$needle]
            );
        }

        return $query;
    }
    private function coverageFilters(Request $request): array
    {
        // Validate once and normalize blank values to consistent defaults.
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'archive' => ['nullable', Rule::in(['current', 'archived', 'all'])],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'quality' => ['nullable', Rule::in(['all', 'incomplete'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return [
            'search' => trim($validated['search'] ?? ''),
            'archive' => $validated['archive'] ?? 'current',
            'status' => $validated['status'] ?? '',
            'quality' => $validated['quality'] ?? 'all',
            'page' => (int) ($validated['page'] ?? 1),
        ];
    }

    private function hasGeographicIssue(Association $association): bool
    {
        // Check stored relationships, not real-world coordinate accuracy.
        return ! $association->areaUnit
            || ! $association->subUnit
            || (int) $association->subUnit->area_unit_id
                !== (int) $association->area_unit_id;
    }

    private function paginateCoverage(
        Collection $records,
        Request $request,
        array $filters
    ): LengthAwarePaginator {
        // Clamp stale page numbers after filters reduce the result count.
        $perPage = 10;
        $lastPage = max(1, (int) ceil($records->count() / $perPage));
        $page = min($filters['page'], $lastPage);

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                // Preserve filters when moving between result pages.
                'query' => [
                'search' => $filters['search'],
                'archive' => $filters['archive'],
                'status' => $filters['status'],
                'quality' => $filters['quality'],
            ],
            ]
        );
    }
}