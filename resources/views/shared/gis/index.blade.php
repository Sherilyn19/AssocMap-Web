<x-dashboard-layout title="GIS Mapping" topbar-title="GIS Mapping">
    @if(request()->routeIs('gis.officer.*'))
    @include('field-officer-user.gis.index')
@else
<div data-gis-page class="gis-page space-y-4">
    @include('shared.gis.publication')
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">BFAR SAAD Phase II</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">GIS Mapping</h1>
            <p class="mt-1 text-sm text-slate-600">Explore association locations and program coverage.</p>
        </div>
        <div class="gis-history-actions">
            <a class="gis-button" href="{{ route('gis.public') }}"
            target="_blank" rel="noopener">
                Public GIS Map
            </a>

            <a class="gis-button" data-gis-history
            href="{{ route(request()->routeIs('gis.officer.*') ? 'gis.officer.archived' : 'gis.archived') }}">
                Archived Locations
            </a>

            <button data-gis-add hidden type="button"
                    class="gis-button gis-save-button">
                + Add Location
            </button>
        </div>
    </header>

    @if(!request()->routeIs('gis.officer.*'))
        @include('admin-user.admin-gis-mapping.export')
    @endif

    {{-- Keep a readable record list available even when the map cannot load. --}}
    <noscript><p class="rounded-lg border border-amber-300 bg-amber-50 p-3">Enable JavaScript to use the map and filters. Location details remain available below.</p></noscript>
    <p data-gis-feedback hidden role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"></p>
    <section data-gis-controls hidden class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filter locations">
        <div class="gis-filters">
            <label class="gis-search">Search locations
                <input data-filter="search" type="search" maxlength="200" placeholder="Association, site, municipality, barangay" class="gis-input">
            </label>
            <label>Municipality<select data-filter="municipality" class="gis-input"><option value="">All municipalities</option></select></label>
            <label>Barangay<select data-filter="barangay" class="gis-input"><option value="">All barangays</option></select></label>
            <label>Program component<select data-filter="component" class="gis-input"><option value="">All components</option></select></label>
            <label>Publication<select data-filter="publication" class="gis-input"><option value="">All locations</option><option value="published">Published</option><option value="unpublished">Unpublished</option></select></label>
            <label>Commodity<select data-filter="commodity" class="gis-input"><option value="">All commodities</option></select></label>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p data-gis-active class="text-xs text-slate-600">No filters applied.</p>
            <button data-gis-clear type="button" class="gis-button">Clear filters</button>
        </div>
    </section>

    <p data-gis-summary role="status" aria-live="polite" class="text-sm font-medium text-slate-700">{{ $records->count() }} location records</p>
    <div class="gis-workspace">
        <section class="gis-map-card rounded-xl border border-slate-200 bg-white shadow-sm" aria-label="Association location map">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2">
                <h2 class="text-sm font-semibold">Internal GIS Map</h2>
                <button data-gis-reset type="button" disabled class="gis-button">Reset map view</button>
            </div>
            <p data-gis-map-status role="status" class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600">Loading map… Location details are available in the list.</p>
            <div data-gis-map class="gis-map" aria-label="Interactive map. Use the location list for the same records."></div>
            <div class="flex flex-wrap items-center gap-4 border-t border-slate-200 px-4 py-3 text-xs text-slate-700" aria-label="Map legend">
                <span><span class="gis-legend-dot gis-published" aria-hidden="true"></span> Published</span>
                <span><span class="gis-legend-dot gis-unpublished" aria-hidden="true"></span> Unpublished</span>
                <span>Numbered pins contain several locations.</span>
            </div>
        </section>

        <aside class="gis-record-panel rounded-xl border border-slate-200 bg-white shadow-sm" aria-label="Location records">
            @include('shared.gis.form')
            <section data-gis-details hidden tabindex="-1" class="border-b border-slate-200 bg-assocmap-bg p-4" aria-labelledby="gis-selected-title">
                <div class="flex items-center justify-between gap-2"><h2 id="gis-selected-title" class="text-sm font-semibold">Selected location</h2><button data-gis-close type="button" class="gis-button">Close</button></div>
                <div data-gis-detail-content class="mt-3 space-y-2"></div>
            </section>
            <div data-gis-record-heading class="border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">Location records</h2><p class="mt-1 text-xs text-slate-600">Select a record to see its details and map position.</p></div>
            <div class="gis-record-list">
                <p data-gis-empty @if($records->isNotEmpty()) hidden @endif class="p-5 text-sm text-slate-600">{{ $records->isEmpty() ? 'No GIS locations have been recorded yet.' : 'No locations match. Change or clear your filters.' }}</p>
                @foreach($records as $record)
                    <article data-gis-row="{{ $record['id'] }}" class="gis-record border-b border-slate-100 p-4">
                        <button data-gis-select="{{ $record['id'] }}" type="button" disabled aria-pressed="false" class="gis-record-button text-left text-sm font-semibold text-slate-900">{{ $record['name'] }}</button>
                        <p class="mt-1 text-sm text-slate-700">{{ $record['association'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ $record['barangay'] }} · {{ $record['municipality'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ $record['component'] }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span class="gis-badge {{ $record['published'] ? 'gis-badge-published' : 'gis-badge-unpublished' }}">{{ $record['published'] ? 'Published' : 'Unpublished' }}</span>
                            @if($record['archived'])<span class="gis-badge bg-slate-100 text-slate-700">Archived association</span>@endif
                            @unless($record['valid'])<span class="gis-badge bg-red-50 text-red-800">Coordinates need review</span>@endunless
                        </div>
                        <a class="gis-button mt-2 inline-block"
                            data-gis-history href="{{ $record['history_url'] }}">
                                View History
                            </a>

                            @if(!$record['can_publish'] && !$record['archived'])
                                <p class="mt-2 text-xs text-amber-800">Not eligible for public visibility</p>
                            @endif
                        <noscript><p class="mt-2 text-xs">{{ $record['valid'] ? $record['latitude'].', '.$record['longitude'] : 'No valid map position' }}</p></noscript>
                    </article>
                @endforeach
            </div>
        </aside>
    </div>

    <details class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
        <summary class="cursor-pointer font-semibold">Associations without location records: {{ $unmapped->count() }} <span class="font-normal text-slate-500">— {{ request()->routeIs('gis.officer.*') ? 'assigned' : 'all' }} associations, independent of filters</span></summary>
        <ul class="mt-3 space-y-2">
            @forelse($unmapped as $association)
                <li>@if(request()->routeIs('gis.officer.*')){{ $association->name }}@else<a class="text-assocmap-primary underline" href="{{ route('admin.associations.show', $association->id) }}">{{ $association->name }}</a>@endif @if($association->is_archived) <span class="text-slate-500">(Archived)</span>@endif</li>
            @empty<li class="text-slate-600">Every association has at least one location record. Check coordinate warnings separately.</li>@endforelse
        </ul>
    </details>
    {{-- Encode names safely so text cannot end this data block or run as HTML. --}}
    <script data-gis-data type="application/json">{!! json_encode($records->values(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</div>
@endif
</x-dashboard-layout>
