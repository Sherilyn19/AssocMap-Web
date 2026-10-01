<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GisLocation;
use App\Services\GisFileProcessor;
use App\Services\GisTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class GisTransferController extends Controller
{
    public function index(Request $request, GisTransferService $imports): mixed
    {
        return view('admin-user.admin-gis-mapping.transfer', ['preview' => $imports->savedPreview((int) $request->attributes->get('assocmap.actor')->id)]);
    }

    public function preview(Request $request, GisTransferService $imports): mixed
    {
        $fields = $request->validate(['format' => ['required', Rule::in(['csv', 'geojson', 'kml', 'zip'])],
            'file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        // Extension and MIME are not authority: the selected parser validates actual content.
        $imports->preview($file->getContent(), $fields['format'], $file->getClientOriginalName(), (int) $request->attributes->get('assocmap.actor')->id);

        return redirect()->route('gis.transfer');
    }

    public function confirm(Request $request, GisTransferService $imports): mixed
    {
        $fields = $request->validate(['token' => ['required', 'uuid'], 'confirmed' => ['accepted']]);
        $count = $imports->confirm($fields['token'], (int) $request->attributes->get('assocmap.actor')->id);

        return redirect()->route('gis.transfer')->with('gis_import_success', $count.' '.($count === 1 ? 'location' : 'locations').' saved as unpublished. Repeating this confirmation does not add copies.');
    }

    public function export(Request $request, GisFileProcessor $processor): mixed
    {
        $fields = $request->validate(['format' => ['required', Rule::in(['csv', 'geojson', 'kml', 'zip'])],
            'search' => ['nullable', 'string', 'max:200'], 'municipality' => ['nullable', 'integer', 'min:1'],
            'barangay' => ['nullable', 'integer', 'min:1'], 'component' => ['nullable', 'integer', 'min:1'],
            'publication' => ['nullable', Rule::in(['published', 'unpublished'])], 'commodity' => ['nullable', 'string', 'max:255']]);
        $query = GisLocation::query()->whereNull('gis_locations.archived_at');
        if (! empty($fields['commodity'])) {
            $query->whereHas('project', fn ($project) => $project->where('is_archived', false)->where('commodity_type', $fields['commodity']));
        }
        foreach (['municipality' => 'area_unit_id', 'barangay' => 'sub_unit_id', 'component' => 'program_component_id'] as $filter => $column) {
            if (! empty($fields[$filter])) {
                $query->whereHas('association', fn ($parent) => $parent->where($column, $fields[$filter]));
            }
        }
        if (! empty($fields['publication'])) {
            $query->where('is_published', $fields['publication'] === 'published');
        }
        if (! empty($fields['search'])) {
            $term = mb_strtolower(trim($fields['search']));
            // Match the joined display text used by the map, including searches across field boundaries.
            $query->leftJoin('associations as export_association', 'export_association.id', '=', 'gis_locations.association_id')
                ->leftJoin('area_units as export_area', 'export_area.id', '=', 'export_association.area_unit_id')
                ->leftJoin('sub_units as export_sub', 'export_sub.id', '=', 'export_association.sub_unit_id')
                ->whereRaw("strpos(lower(concat_ws(' ', COALESCE(NULLIF(gis_locations.location_name, ''), 'Unnamed location'), COALESCE(export_association.name, 'Association unavailable'), COALESCE(export_area.name, 'Not recorded'), COALESCE(export_sub.name, 'Not recorded'))), ?) > 0", [$term]);
        }
        $rows = $query->orderBy('gis_locations.location_name')->orderBy('gis_locations.id')->limit(1001)
            ->get(['gis_locations.association_id', 'gis_locations.location_name', 'gis_locations.latitude', 'gis_locations.longitude'])
            ->map(fn ($location) => ['association_id' => (string) $location->association_id, 'location_name' => $location->location_name,
                'latitude' => $location->latitude, 'longitude' => $location->longitude, 'crs' => 'EPSG:4326'])->all();
        if (! count($rows) || count($rows) > 1000) {
            throw ValidationException::withMessages(['export' => 'Export requires between 1 and 1,000 locations. Refine the filters.']);
        }
        $content = $processor->process('export', $fields['format'], json_encode($rows, JSON_THROW_ON_ERROR));
        $types = ['csv' => 'text/csv', 'geojson' => 'application/geo+json', 'kml' => 'application/vnd.google-earth.kml+xml', 'zip' => 'application/zip'];

        return response($content)->header('Content-Type', $types[$fields['format']])
            ->header('Content-Disposition', 'attachment; filename="assocmap-internal-locations-'.now()->format('Ymd-His').'.'.$fields['format'].'"')
            ->header('Cache-Control', 'no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
