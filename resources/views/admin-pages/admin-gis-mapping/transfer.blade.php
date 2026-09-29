<x-dashboard-layout title="Import GIS locations">
    <main class="mx-auto min-w-0 max-w-5xl space-y-5">
        <header><a class="text-sm text-assocmap-primary underline" href="{{ route('gis.index') }}">Back to GIS Mapping</a><h1 class="mt-3 text-2xl font-bold">Import GIS locations</h1><p class="mt-2 text-sm text-slate-600">Upload → review validation → confirm. Existing locations are never overwritten.</p></header>
        @if(session('gis_import_success'))<p role="status" class="rounded-lg border border-emerald-300 bg-emerald-50 p-4">{{ session('gis_import_success') }}</p>@endif
        @if($errors->any())<div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-4"><h2 class="font-semibold">Import could not proceed</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">1. Select a file</h2>
            <p class="mt-2 text-sm text-slate-600">Points only · 5 MiB maximum · 1,000 records · existing association IDs. CSV columns: association_id, location_name, latitude, longitude, crs (EPSG:4326). GeoJSON uses these properties with Point geometry. KML uses name and an association_id ExtendedData field. Shapefile uses assoc_id and loc_name with .shp, .shx, .dbf, .prj and optional .cpg.</p>
            <form data-gis-transfer-form action="{{ route('gis.import.preview') }}" method="post" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <label class="block text-sm font-medium">Format<select name="format" required class="mt-1 block min-h-11 w-full rounded-lg border border-slate-300 p-2">@foreach(['csv'=>'CSV','geojson'=>'GeoJSON','kml'=>'KML','zip'=>'Shapefile ZIP'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium">GIS file<input class="mt-1 block w-full rounded border border-slate-300 p-2" type="file" name="file" required accept=".csv,.geojson,.json,.kml,.zip"></label>
                <button class="am-user-button am-user-button-primary" type="submit">Process and preview</button>
                <p data-transfer-status role="status" class="text-sm text-slate-600"></p>
            </form>
        </section>
        @if($preview)
            <section class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="preview-title">
                <h2 id="preview-title" class="font-semibold">2. Review validation</h2>
                <p class="mt-2 break-words text-sm">{{ $preview['filename'] }} · {{ strtoupper($preview['format']) }} · {{ $preview['detected_crs'] }} → {{ $preview['target_crs'] }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach(['total'=>'Total rows','accepted'=>'Accepted','rejected'=>'Rejected','duplicates'=>'Duplicates'] as $key=>$label)<div class="rounded-lg bg-slate-50 p-3"><dt class="text-sm">{{ $label }}</dt><dd class="text-xl font-semibold">{{ $preview[$key] }}</dd></div>@endforeach</dl>
                <h3 class="mt-4 font-semibold">Warnings ({{ count($preview['warnings']) }})</h3><ul class="list-inside list-disc text-sm">@foreach($preview['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                <h3 class="mt-4 font-semibold">Errors ({{ count($preview['errors']) }})</h3><ul class="max-h-48 overflow-auto text-sm text-red-800">@forelse($preview['errors'] as $error)<li>{{ $error }}</li>@empty<li class="text-slate-700">No validation errors.</li>@endforelse</ul>
                @if($preview['records'])
                    <section data-gis-viewer class="gis-page mt-4 overflow-hidden rounded-lg border border-slate-200" aria-label="Unsaved import map preview">
                        <h3 class="p-3 text-sm font-semibold">{{ $preview['completed'] ? 'Import completed — locations saved as unpublished' : 'Unsaved locations — preview only' }}</h3>
                        <p data-viewer-status role="status" class="p-3 text-sm">Loading preview map…</p>
                        <div data-viewer-map class="gis-map" aria-label="Preview of imported points"></div>
                        @php($mapRows = collect($preview['records'])->map(fn($row) => ['name' => $row['location_name'], 'association' => $row['association'], 'municipality' => '', 'barangay' => '', 'latitude' => (float)$row['latitude'], 'longitude' => (float)$row['longitude'], 'published' => false]))
                        <script type="application/json" data-viewer-records>{!! json_encode($mapRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
                    </section>
                    <ul class="mt-3 text-sm">@foreach(collect($preview['records'])->unique('association_id') as $association)<li>Association {{ $association['association_id'] }}: {{ $association['association'] }}</li>@endforeach</ul>
                @endif
                <div class="mt-4 max-h-80 overflow-auto" tabindex="0" aria-label="Preview records"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Row</th><th class="p-2">Association ID</th><th class="p-2">Location</th><th class="p-2">Latitude</th><th class="p-2">Longitude</th></tr></thead><tbody>@foreach($preview['records'] as $row)<tr class="border-t"><td class="p-2">{{ $row['row'] }}</td><td class="p-2">{{ $row['association_id'] }}</td><td class="break-words p-2">{{ $row['location_name'] }}</td><td class="p-2">{{ $row['latitude'] }}</td><td class="p-2">{{ $row['longitude'] }}</td></tr>@endforeach</tbody></table></div>
                @if($preview['accepted'] > 0 && !$preview['completed'])
                    <form data-gis-transfer-form class="mt-5 space-y-3" action="{{ route('gis.import.confirm') }}" method="post">@csrf<input type="hidden" name="token" value="{{ $preview['token'] }}"><h3 class="font-semibold">3. Confirm import</h3><label class="flex items-start gap-2 text-sm"><input class="mt-1" type="checkbox" name="confirmed" value="1" required>I reviewed these records and confirm adding {{ $preview['accepted'] }} unpublished locations.</label><button class="am-user-button am-user-button-primary" type="submit">Save unpublished locations</button><p data-transfer-status role="status" class="text-sm text-slate-600"></p></form>
                @endif
            </section>
        @endif
    </main>
</x-dashboard-layout>
