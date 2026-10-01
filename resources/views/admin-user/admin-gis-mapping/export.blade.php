<section class="rounded-xl border border-slate-200 bg-white p-4" aria-label="GIS file tools">
    <div class="flex flex-wrap items-center gap-3">
        <a class="gis-button" href="{{ route('gis.transfer') }}">Import locations</a>
        <form data-gis-export action="{{ route('gis.export') }}" method="post" class="flex flex-wrap items-center gap-3">
            @csrf
            <label class="text-sm">Export format<select name="format" class="gis-input">@foreach(['csv'=>'CSV','geojson'=>'GeoJSON','kml'=>'KML','zip'=>'Shapefile ZIP'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            @foreach(['search','municipality','barangay','component','publication','commodity'] as $filter)<input type="hidden" name="{{ $filter }}" value="">@endforeach
            <button type="submit" class="gis-button">Download filtered locations</button>
        </form>
        <p data-gis-export-scope class="text-xs text-slate-600">{{ $records->count() }} records. Includes unpublished records unless the Published filter is selected. Internal administrator download.</p>
    </div>
    <p data-gis-export-status role="status" class="mt-2 text-sm text-slate-700"></p>
</section>
