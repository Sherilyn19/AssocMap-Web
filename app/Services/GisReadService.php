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
        $query = GisLocation::query()->whereNull('archived_at');
        if ($actor === null) {
            $query->publiclyVisible();
        } else {
            abort_unless($actor->is_active, 403);
            $role = $actor->role?->role_name;
            abort_unless(in_array($role, ['System Administrator', 'Field Officer', 'Association Member'], true), 403);
            $query->whereHas('association', function (Builder $association) use ($actor, $role): void {
                $association->where('is_archived', false);
                if ($role === 'Field Officer') {
                    $association->where('field_officer_id', $actor->id);
                } elseif ($role === 'Association Member') {
                    // An unassigned account must never fall back to all associations.
                    $association->whereKey($actor->association_id ?? 0);
                }
            });
            $query->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])
                ->whereNotNull('location_name')->whereRaw("BTRIM(location_name) <> ''");
        }

        foreach (['municipality' => 'areaUnit', 'barangay' => 'subUnit', 'component' => 'programComponent'] as $field => $relation) {
            if (($filters[$field] ?? '') !== '') {
                $query->whereHas('association.'.$relation, fn (Builder $related) => $related->where('name', $filters[$field]));
            }
        }
        if (($filters['search'] ?? '') !== '') {
            // strpos performs a literal search; percent and underscore are not wildcards.
            $term = mb_strtolower($filters['search']);
            $query->where(fn (Builder $search) => $search
                ->whereRaw('strpos(lower(location_name), ?) > 0', [$term])
                ->orWhereHas('association', fn (Builder $association) => $association->whereRaw('strpos(lower(name), ?) > 0', [$term])));
        }

        if (($filters['commodity'] ?? '') !== '') {
            $query->whereHas('project', fn (Builder $project) => $project->where('is_archived', false)->where('commodity_type', $filters['commodity']));
        }

        $page = (int) ($filters['page'] ?? 1);
        $results = $query->select(['id', 'association_id', 'project_id', 'location_name', 'latitude', 'longitude', 'is_published'])
            ->with(['association:id,name,area_unit_id,sub_unit_id,program_component_id', 'association.areaUnit:id,name',
                'association.subUnit:id,name', 'association.programComponent:id,name', 'project:id,association_id,title,commodity_type,is_archived'])
            ->orderBy('location_name')->orderBy('id')->simplePaginate(200, ['*'], 'page', $page);

        $records = $results->getCollection()->map(function (GisLocation $location) use ($actor): array {
            $association = $location->association;
            // Explicitly allow fields. Never return models or the administrator payload.
            $record = [
                'name' => $location->location_name,
                'association' => $association->name,
                'municipality' => $association->areaUnit?->name ?? 'Not recorded',
                'barangay' => $association->subUnit?->name ?? 'Not recorded',
                'component' => $association->programComponent?->name ?? 'Not recorded',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'project_title' => $location->project && ! $location->project->is_archived ? $location->project->title : null,
                'commodity' => $location->project && ! $location->project->is_archived ? $location->project->commodity_type : null,
            ];
            if ($actor !== null) {
                $record['published'] = $location->is_published;
            }

            return $record;
        });

        return ['records' => $records->values()->all(), 'crs' => 'EPSG:4326', 'page' => $page,
            'page_size' => 200, 'has_more' => $results->hasMorePages()];
    }
}
