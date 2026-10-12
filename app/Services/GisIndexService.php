<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\GisLocation;
use App\Models\Project;
use App\Support\GisRevision;

final class GisIndexService
{
    /**
     * Describe the first known public-visibility restriction.
     * The publication flag alone does not prove public eligibility.
     */
    private function visibilityReason(
        GisLocation $location,
        ?Association $association
    ): string {
        if (! $location->is_published) {
            return 'Not marked for publication.';
        }

        if ($location->archived_at !== null) {
            return 'The location is archived.';
        }

        if (! $association || $association->is_archived) {
            return 'The association is archived or unavailable.';
        }

        if ($association->status?->status_name !== 'Active') {
            return 'The association is not Active.';
        }

        // Missing or nonnumeric coordinates cannot identify a valid map position.
        if (! is_numeric($location->latitude)
            || ! is_numeric($location->longitude)) {
            return 'Latitude and longitude must contain valid numbers.';
        }

        $latitude = (float) $location->latitude;
        $longitude = (float) $location->longitude;

        // Reject infinite values and coordinates outside geographic limits.
        // Zero remains a valid coordinate.
        if (! is_finite($latitude)
            || ! is_finite($longitude)
            || $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180) {
            return 'Latitude must be between -90 and 90, and longitude between -180 and 180.';
        }

        if (! is_string($location->location_name)
            || trim($location->location_name) === '') {
            return 'A location name is required.';
        }

        return 'The location does not meet current public GIS eligibility rules.';
    }

    public function overview(?int $officerId = null): array
    {
        // Load related names together. The map never needs member or account details.
        // Reuse the public-map eligibility rules instead of duplicating them in JavaScript.
        $publicLocations = GisLocation::query()
            ->publiclyVisible()
            ->select('id');
        $locations = GisLocation::query()
            ->whereNull('archived_at')
            ->when($officerId !== null, fn ($query) => $query->whereHas('association', fn ($parent) => $parent->where('field_officer_id', $officerId)->where('is_archived', false)))
            ->select(['id', 'association_id', 'project_id', 'location_name', 'latitude', 'longitude', 'is_published', 'created_at', 'updated_at'])
            ->selectRaw(GisRevision::SQL)
            ->selectRaw(
                'gis_locations.id IN ('.$publicLocations->toSql().') AS public_visible',
                $publicLocations->getBindings()
            )
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,program_component_id,status_id,is_archived',
                'association.areaUnit:id,name',
                'association.subUnit:id,name,area_unit_id',
                'association.programComponent:id,name',
                'association.status:id,status_name',
                'project:id,association_id,title,commodity_type,is_archived',
            ])
            ->orderBy('location_name')->orderBy('id')->get();

        $records = $locations->map(function (GisLocation $location) use ($officerId): array {
            $association = $location->association;
            $latitude = $location->latitude;
            $longitude = $location->longitude;
            // Zero is a valid coordinate. Missing values must not become zero on the map.
            $valid = is_numeric($latitude) && is_numeric($longitude)
                && is_finite((float) $latitude) && is_finite((float) $longitude)
                && (float) $latitude >= -90 && (float) $latitude <= 90
                && (float) $longitude >= -180 && (float) $longitude <= 180;

            return [
                'id' => $location->id,
                // A saved publication flag alone does not prove public visibility.
                'public_visible' => (bool) $location->public_visible,
                // Explain why a saved location is not currently visible on the public map.
                'visibility_reason' => $location->public_visible
                    ? null
                    : $this->visibilityReason($location, $association),
                // Effective public visibility is separate from the saved publish flag.
                'visibility_state' => $location->public_visible
                    ? 'public'
                    : ($location->is_published ? 'marked_hidden' : 'unmarked'),
                // Management metadata remains internal; public readers use GisReadService.
                'history_url' => route(
                    $officerId === null ? 'gis.history' : 'gis.officer.history',
                    $location->id
                ),
                'can_publish' => $association !== null
                    && ! $association->is_archived
                    && $association->status?->status_name === 'Active',
                'association_id' => $location->association_id,
                'project_id' => $location->project_id,
                'project_title' => $location->project?->title,
                'commodity' => $location->project?->commodity_type,
                'project_archived' => (bool) $location->project?->is_archived,
                'revision' => $location->revision,
                'update_url' => route($officerId === null ? 'gis.update' : 'gis.officer.update', $location->id),
                'archive_url' => route($officerId === null ? 'gis.archive' : 'gis.officer.archive', $location->id),
                'publication_url' => route(($officerId === null ? 'gis.' : 'gis.officer.').($location->is_published ? 'unpublish' : 'publish'), $location->id),
                'editable' => $association !== null && ! $association->is_archived,
                'name' => $location->location_name ?: 'Unnamed location',
                'association' => $association?->name ?? 'Association unavailable',
                'association_url' => $association && $officerId === null ? route('admin.associations.show', $association->id) : null,
                'municipality_id' => $association?->area_unit_id,
                'municipality' => $association?->areaUnit?->name ?? 'Not recorded',
                'barangay_id' => $association?->sub_unit_id,
                'barangay' => $association?->subUnit?->name ?? 'Not recorded',
                'component_id' => $association?->program_component_id,
                'component' => $association?->programComponent?->name ?? 'Not recorded',
                'status' => $association?->status?->status_name ?? 'Not recorded',
                'archived' => (bool) $association?->is_archived,
                'latitude' => $valid ? (float) $latitude : null,
                'longitude' => $valid ? (float) $longitude : null,
                'latitude_text' => $latitude === null ? '' : (string) $latitude,
                'longitude_text' => $longitude === null ? '' : (string) $longitude,
                'created_at' => $location->created_at ? $location->created_at->timezone('Asia/Manila')->format('M d, Y h:i A').' PHT' : '',
                'updated_at' => $location->updated_at ? $location->updated_at->timezone('Asia/Manila')->format('M d, Y h:i A').' PHT' : '',
                'valid' => $valid,
                'published' => $location->is_published,
            ];
        });

        // This separate register is clearly labeled as all associations, outside map filters.
        $unmapped = Association::query()->select(['id', 'name', 'is_archived'])
            ->when($officerId !== null, fn ($query) => $query->where('field_officer_id', $officerId)->where('is_archived', false))
            ->whereDoesntHave('gisLocations', fn ($query) => $query->whereNull('archived_at'))->orderBy('name')->get();

        $associations = Association::query()->select(['id', 'name'])->where('is_archived', false)
            ->when($officerId !== null, fn ($query) => $query->where('field_officer_id', $officerId))->orderBy('name')->get();

        $projects = Project::query()->select(['id', 'association_id', 'title', 'commodity_type'])
            ->where('is_archived', false)->whereIn('association_id', $associations->pluck('id'))->orderBy('title')->get();

        // Archive scope matches the existing history controller, including reassignment.
        $archivedCount = GisLocation::query()
            ->whereNotNull('archived_at')
            ->when(
                $officerId !== null,
                fn ($query) => $query->whereHas(
                    'association',
                    fn ($association) => $association
                        ->where('field_officer_id', $officerId)
                )
            )
            ->count();

        $visibleCount = $records->where('public_visible', true)->count();

        $summary = [
            'current' => $records->count(),
            'visible' => $visibleCount,
            'hidden' => $records->count() - $visibleCount,
            'archived' => $archivedCount,
        ];

        return compact(
            'records',
            'unmapped',
            'associations',
            'projects',
            'summary'
        );
    }
}
