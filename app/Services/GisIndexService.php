<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\GisLocation;
use App\Support\GisRevision;

final class GisIndexService
{
    public function overview(): array
    {
        // Load related names together. The map never needs member or account details.
        $locations = GisLocation::query()
            ->select(['id', 'association_id', 'location_name', 'latitude', 'longitude', 'is_published', 'created_at', 'updated_at'])
            ->selectRaw(GisRevision::SQL)
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,program_component_id,status_id,is_archived',
                'association.areaUnit:id,name',
                'association.subUnit:id,name,area_unit_id',
                'association.programComponent:id,name',
                'association.status:id,status_name',
            ])
            ->orderBy('location_name')->orderBy('id')->get();

        $records = $locations->map(function (GisLocation $location): array {
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
                'association_id' => $location->association_id,
                'revision' => $location->revision,
                'update_url' => route('gis.update', $location->id),
                'publication_url' => route($location->is_published ? 'gis.unpublish' : 'gis.publish', $location->id),
                'editable' => $association !== null && ! $association->is_archived,
                'name' => $location->location_name ?: 'Unnamed location',
                'association' => $association?->name ?? 'Association unavailable',
                'association_url' => $association ? route('admin.associations.show', $association->id) : null,
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
            ->whereDoesntHave('gisLocations')->orderBy('name')->get();

        $associations = Association::query()->select(['id', 'name'])->where('is_archived', false)->orderBy('name')->get();

        return compact('records', 'unmapped', 'associations');
    }
}
