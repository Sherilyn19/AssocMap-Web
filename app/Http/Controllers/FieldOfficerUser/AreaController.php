<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\AreaUnit;
use App\Services\FieldOfficerUserAccess;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

final class AreaController extends Controller
{
    public function index(
        Request $request,
        SessionUserResolver $resolver,
        FieldOfficerUserAccess $access
    ) {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'archive' => ['nullable', Rule::in(['current', 'archived', 'all'])],
            'quality' => ['nullable', Rule::in(['all', 'incomplete'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $actor = $resolver->resolve($request);
        abort_unless($actor, 403);

        // Every record and total starts from the officer's current assignments.
        $assigned = $access->associations($actor)
            ->with(['areaUnit', 'subUnit', 'status'])
            ->withCount([
                'members as current_members_count' =>
                    fn ($query) => $query->where('is_archived', false),
                'projects as retained_projects_count' =>
                    fn ($query) => $query->where('is_archived', false),
            ])
            ->orderBy('name')
            ->get();

        // Detect missing geography and barangays linked to another municipality.
        $hasIssue = static fn ($association): bool =>
            ! $association->areaUnit
            || ! $association->subUnit
            || (int) $association->subUnit->area_unit_id
                !== (int) $association->area_unit_id;

        $summary = [
            'municipalities' => $assigned
                ->filter(fn ($item) => $item->areaUnit)
                ->pluck('area_unit_id')->unique()->count(),
            'barangays' => $assigned
                ->reject($hasIssue)
                ->pluck('sub_unit_id')->unique()->count(),
            'associations' => $assigned->count(),
            'issues' => $assigned->filter($hasIssue)->count(),
        ];

        $search = trim($filters['search'] ?? '');
        $archive = $filters['archive'] ?? 'current';
        $quality = $filters['quality'] ?? 'all';

        $filtered = $assigned->filter(function ($item) use (
            $search, $archive, $quality, $hasIssue
        ) {
            if ($archive !== 'all'
                && (bool) $item->is_archived !== ($archive === 'archived')) {
                return false;
            }

            if ($quality === 'incomplete' && ! $hasIssue($item)) {
                return false;
            }

            $text = implode(' ', [
                $item->name,
                $item->areaUnit?->name,
                $item->subUnit?->name,
            ]);

            return $search === '' || mb_stripos($text, $search) !== false;
        });

        $issues = $filtered->filter($hasIssue)->values();

        // Unknown municipalities remain in the issues list instead of disappearing.
        $rows = $filtered
            ->filter(fn ($item) => $item->areaUnit)
            ->groupBy('area_unit_id')
            ->map(function ($items) use ($hasIssue) {
                $area = $items->first()->areaUnit;

                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'archived' => (bool) $area->is_archived,
                    'barangays' => $items->reject($hasIssue)
                        ->pluck('subUnit.name')->unique()->sort()->values(),
                    'associations' => $items->count(),
                    'members' => $items->sum('current_members_count'),
                    'projects' => $items->sum('retained_projects_count'),
                    'issues' => $items->filter($hasIssue)->count(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $page = min(
            (int) ($filters['page'] ?? 1),
            max(1, (int) ceil($rows->count() / 10))
        );

        $areas = new LengthAwarePaginator(
            $rows->forPage($page, 10)->values(),
            $rows->count(),
            10,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('field-officer-user.areas.index', compact(
            'areas', 'summary', 'issues', 'search', 'archive', 'quality'
        ));
    }

    public function show(
    Request $request,
    int $areaUnit,
    SessionUserResolver $resolver,
    FieldOfficerUserAccess $access
) {
    $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

    $actor = $resolver->resolve($request);
    abort_unless($actor, 403);

    $scope = $access->associations($actor);

    // A municipality is available only through the officer's assignments.
    $area = AreaUnit::query()
        ->whereIn('id', (clone $scope)->select('area_unit_id'))
        ->findOrFail($areaUnit);

    $associations = (clone $scope)
        ->where('area_unit_id', $area->id)
        ->with(['subUnit', 'status', 'programComponent'])
        ->withCount([
            // Count only non-archived members and projects.
            'members as current_members_count' =>
                fn ($query) => $query->where('is_archived', false),

            'projects as retained_projects_count' =>
                fn ($query) => $query->where('is_archived', false),

            // Count saved GIS locations that have not been archived.
            'gisLocations as locations_count' =>
                fn ($query) => $query->whereNull('archived_at'),
        ])
        ->orderBy('name')
        ->orderBy('id')
        ->paginate(10)
        ->withQueryString();

    return view('field-officer-user.areas.show', compact(
        'area', 'associations'
    ));
}

public function details(
    Request $request,
    int $areaUnit,
    int $association,
    string $section,
    SessionUserResolver $resolver,
    FieldOfficerUserAccess $access
) {
    $request->validate([
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    abort_unless(
        in_array($section, ['association', 'members', 'projects', 'gis'], true),
        404
    );

    $actor = $resolver->resolve($request);
    abort_unless($actor, 403);

    // Scope the association before retrieving members, projects, or locations.
    // This prevents access through a copied URL or an outdated open dialog.
    $association = $access->associations($actor)
        ->where('area_unit_id', $areaUnit)
        ->with(['areaUnit', 'subUnit', 'programComponent', 'status'])
        ->withCount([
            'members as current_members_count' =>
                fn ($query) => $query->where('is_archived', false),
            'projects as retained_projects_count' =>
                fn ($query) => $query->where('is_archived', false),
            'gisLocations as mapped_locations_count' =>
                fn ($query) => $query->whereNull('archived_at'),
        ])
        ->findOrFail($association);

    // Only load the requested collection. Large lists remain paginated.
    $records = match ($section) {
        'members' => $association->members()
            ->where('is_archived', false)
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(8)->withQueryString(),

        'projects' => $association->projects()
            ->where('is_archived', false)
            ->with('status')
            ->orderBy('title')->orderBy('id')
            ->paginate(8)->withQueryString(),

        'gis' => $association->gisLocations()
            ->whereNull('archived_at')
            ->orderBy('location_name')->orderBy('id')
            ->paginate(8)->withQueryString(),

        default => null,
    };

    // A small GIS preview is included in the association overview.
    $locations = $section === 'association'
        ? $association->gisLocations()
            ->whereNull('archived_at')
            ->orderBy('location_name')->orderBy('id')
            ->limit(3)->get()
        : collect();

    return view('field-officer-user.areas.drawer', compact(
        'association', 'section', 'records', 'locations'
    ));
}
}