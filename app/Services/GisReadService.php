<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GisLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class GisReadService
{
    public function locations(array $filters, ?User $actor = null): array
    {
        // Records and dropdown choices share the same authorization boundary.
        $query = $this->visibleQuery($actor);

        foreach ([
            'municipality' => 'areaUnit',
            'barangay' => 'subUnit',
            'component' => 'programComponent',
        ] as $field => $relation) {
            if (($filters[$field] ?? '') !== '') {
                $query->whereHas(
                    'association.'.$relation,
                    fn (Builder $related) => $related
                        ->where('name', $filters[$field])
                );
            }
        }

        if (($filters['search'] ?? '') !== '') {
            // Literal search: percent and underscore are not wildcards.
            $term = mb_strtolower($filters['search']);

            $query->where(
                fn (Builder $search) => $search
                    ->whereRaw(
                        'strpos(lower(gis_locations.location_name), ?) > 0',
                        [$term]
                    )
                    ->orWhereHas(
                        'association',
                        fn (Builder $association) => $association
                            ->whereRaw(
                                'strpos(lower(associations.name), ?) > 0',
                                [$term]
                            )
                    )
            );
        }

        if (($filters['commodity'] ?? '') !== '') {
            $query->whereHas(
                'project',
                fn (Builder $project) => $project
                    ->where('is_archived', false)
                    ->where('commodity_type', $filters['commodity'])
            );
        }

        $page = (int) ($filters['page'] ?? 1);

        $results = $query
            ->select([
                'gis_locations.id',
                'gis_locations.association_id',
                'gis_locations.project_id',
                'gis_locations.location_name',
                'gis_locations.latitude',
                'gis_locations.longitude',
                'gis_locations.is_published',
            ])
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,program_component_id',
                'association.areaUnit:id,name',
                'association.subUnit:id,name',
                'association.programComponent:id,name',
                'project:id,association_id,title,commodity_type,is_archived',
            ])
            ->orderBy('gis_locations.location_name')
            ->orderBy('gis_locations.id')
            ->simplePaginate(200, ['*'], 'page', $page);

        $records = $results->getCollection()->map(
            function (GisLocation $location) use ($actor): array {
                $association = $location->association;
                $project = $location->project;

                $showProject = $project !== null && !$project->is_archived;

                // Return an explicit allowlist, never models or management data.
                $record = [
                    'name' => $location->location_name,
                    'association' => $association->name,
                    'municipality' => $association->areaUnit?->name
                        ?? 'Not recorded',
                    'barangay' => $association->subUnit?->name
                        ?? 'Not recorded',
                    'component' => $association->programComponent?->name
                        ?? 'Not recorded',
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'project_title' => $showProject ? $project->title : null,
                    'commodity' => $showProject ? $project->commodity_type : null,
                ];

                if ($actor !== null) {
                    $record['published'] = $location->is_published;
                }

                return $record;
            }
        );

        return [
            'records' => $records->values()->all(),
            'crs' => 'EPSG:4326',
            'page' => $page,
            'page_size' => 200,
            'has_more' => $results->hasMorePages(),
        ];
    }

    /**
     * Use one eligibility boundary for records and filter choices.
     * Public requests never receive internal association choices.
     */
    private function visibleQuery(?User $actor): Builder
    {
        $query = GisLocation::query()
            ->whereNull('gis_locations.archived_at');

        if ($actor === null) {
            return $query->publiclyVisible();
        }

        abort_unless($actor->is_active, 403);

        $role = $actor->role?->role_name;

        abort_unless(
            in_array(
                $role,
                ['System Administrator', 'Field Officer', 'Association Member'],
                true
            ),
            403
        );

        if ($role === 'Association Member') {
            // Apply publication and ownership rules to records and filter choices.
            return $query
                ->publiclyVisible()
                ->where('gis_locations.association_id', $actor->association_id ?? 0);
        }

        $query->whereHas(
            'association',
            function (Builder $association) use ($actor, $role): void {
                $association->where('is_archived', false);

                if ($role === 'Field Officer') {
                    $association->where('field_officer_id', $actor->id);
                }
            }
        );

        return $query
            ->whereBetween('gis_locations.latitude', [-90, 90])
            ->whereBetween('gis_locations.longitude', [-180, 180])
            ->whereNotNull('gis_locations.location_name')
            ->whereRaw("BTRIM(gis_locations.location_name) <> ''");
    }

    /**
     * Build dropdown choices from all eligible locations, not just one page.
     * Only geographic names, program components, and commodities are returned.
     */
    public function filterOptions(?User $actor = null): array
    {
        $rows = $this->visibleQuery($actor)
            ->join('associations as filter_association', function ($join): void {
                $join->on(
                    'filter_association.id',
                    '=',
                    'gis_locations.association_id'
                );
            })
            ->leftJoin(
                'area_units as filter_area',
                'filter_area.id',
                '=',
                'filter_association.area_unit_id'
            )
            ->leftJoin(
                'sub_units as filter_barangay',
                'filter_barangay.id',
                '=',
                'filter_association.sub_unit_id'
            )
            ->leftJoin(
                'program_components as filter_component',
                'filter_component.id',
                '=',
                'filter_association.program_component_id'
            )
            ->leftJoin('projects as filter_project', function ($join): void {
                $join->on(
                    'filter_project.id',
                    '=',
                    'gis_locations.project_id'
                )->where('filter_project.is_archived', false);
            })
            ->select([
                'filter_area.name as municipality',
                'filter_barangay.name as barangay',
                'filter_component.name as component',
                'filter_project.commodity_type as commodity',
            ])
            ->distinct()
            ->get();

        $options = [];

        foreach ([
            'municipality',
            'barangay',
            'component',
            'commodity',
        ] as $field) {
            $options[$field] = $rows
                ->pluck($field)
                ->filter(
                    fn ($value) => is_string($value) && trim($value) !== ''
                )
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        }

        return $options;
    }
}