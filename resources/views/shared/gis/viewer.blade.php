<x-public-layout title="Association location map" current="map" :internal="$internal">
    @unless($internal)
    <main data-gis-viewer id="main-content" tabindex="-1" class="gis-page am-public-gis">
        <div class="am-gis-shell">
            <aside id="public-map-filters" class="am-gis-filter-rail" aria-label="Map search and filters">
                <div class="am-gis-intro">
                    <h1>Association location map</h1>
                    <p>Explore published association locations and program coverage.</p>
                </div>
                <div class="am-gis-filter-heading"><h2>Filters</h2>@if($data !== null)<span>{{ count($data['records']) }} {{ count($data['records']) === 1 ? 'location' : 'locations' }}</span>@endif</div>
                <form method="get" class="am-gis-controls" aria-label="Filter locations">
                    <label>Search association or location<input class="gis-input" name="search" type="search" maxlength="200" value="{{ $filters['search'] ?? '' }}"></label>
                    <div class="am-gis-filter-fields">
                        @foreach(['municipality' => 'Municipality', 'barangay' => 'Barangay', 'component' => 'Program component', 'commodity' => 'Commodity'] as $field => $label)
                            <label>{{ $label }}<input class="gis-input" name="{{ $field }}" maxlength="255" placeholder="Exact name" value="{{ $filters[$field] ?? '' }}"></label>
                        @endforeach
                    </div>
                    <div class="am-gis-filter-actions">
                        <button class="gis-button gis-save-button" type="submit">Apply filters</button>
                        <a class="gis-button" href="{{ route($internal ? 'gis.viewer' : 'gis.public') }}">Clear filters</a>
                    </div>
                    <p class="mt-3 text-xs text-slate-600">Area, component and commodity filters match the full name.</p>
                    @if($errors->any())<p role="alert" class="mt-3 text-sm text-red-800">{{ $errors->first() }}</p>@endif
                </form>
            </aside>
            <div class="am-gis-map-area">
                <button type="button" class="gis-button am-gis-filter-button" data-public-filter-toggle aria-controls="public-map-filters" aria-expanded="true" hidden><span aria-hidden="true">&larr;</span><span data-public-filter-label>Hide filters</span></button>
                @if($data === null)
                    <section role="alert" class="am-gis-unavailable"><h2 class="font-semibold">Locations are temporarily unavailable</h2><p class="mt-2">Please reload this page to try again.</p></section>
                @else
                    <section class="am-gis-map-panel" aria-label="Location map">
                        <div data-viewer-map class="gis-map" aria-label="Interactive association map"></div>
                        <div class="am-gis-status">
                            <p data-viewer-status role="status">Loading map&hellip; The location list remains available.</p>
                            <p role="status" class="am-gis-count">{{ count($data['records']) }} {{ count($data['records']) === 1 ? 'location' : 'locations' }} on page {{ $data['page'] }}. {{ $internal ? 'Publication status is shown for each location.' : 'Only published locations are shown.' }}</p>
                            <p class="am-gis-map-help">Select a pin or a location in the list. Numbered pins group nearby locations. Coordinates use WGS84.</p>
                        </div>
                    </section>
                    <details class="am-gis-locations" data-public-locations aria-label="Location list">
                        <summary aria-controls="public-map-location-list" aria-expanded="false">Locations ({{ count($data['records']) }})</summary>
                        <div id="public-map-location-list" class="am-gis-location-list" tabindex="0" aria-label="Scrollable locations">
                            @forelse($data['records'] as $record)
                                <article class="break-words border-b border-slate-100 p-4">
                                    <h3 class="font-semibold">{{ $record['name'] }}</h3>
                                    <p class="mt-1 text-sm">{{ $record['association'] }}</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ $record['barangay'] }} &middot; {{ $record['municipality'] }}</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ $record['component'] }}</p>
                                    @if($record['project_title'])<p class="mt-2 text-sm"><span class="font-semibold">Project:</span> {{ $record['project_title'] }}</p>@endif
                                    @if($record['commodity'])<p class="mt-1 text-sm"><span class="font-semibold">Commodity:</span> {{ $record['commodity'] }}</p>@endif
                                    <p class="mt-1 text-xs text-slate-600">{{ $record['latitude'] }}, {{ $record['longitude'] }}</p>
                                    @if($internal)<p class="mt-2 text-xs font-semibold">{{ $record['published'] ? 'Published' : 'Unpublished — internal' }}</p>@endif
                                    <button data-viewer-select="{{ $loop->index }}" type="button" hidden class="gis-button mt-3">Show on map</button>
                                </article>
                            @empty
                                <p class="p-5 text-sm text-slate-600">No locations match these filters. Try clearing the filters.</p>
                            @endforelse
                        </div>
                        <nav class="am-gis-pages" aria-label="Location pages">
                            @if($data['page'] > 1)<a class="gis-button" href="{{ request()->fullUrlWithQuery(['page' => $data['page'] - 1]) }}">Previous page</a>@endif
                            @if($data['has_more'])<a class="gis-button" href="{{ request()->fullUrlWithQuery(['page' => $data['page'] + 1]) }}">Next page</a>@endif
                        </nav>
                    </details>
                @endif
            </div>
        </div>
        @if($data !== null)
            <script type="application/json" data-viewer-records>{!! json_encode($data['records'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        @endif
        <noscript><p>Enable JavaScript for the interactive map. Filters and location details work without it.</p></noscript>
        <script>
            (() => {
                const root = document.querySelector('[data-gis-viewer]');
                const shell = root.querySelector('.am-gis-shell');
                const panel = root.querySelector('#public-map-filters');
                const toggle = root.querySelector('[data-public-filter-toggle]');
                const label = root.querySelector('[data-public-filter-label]');
                const locations = root.querySelector('[data-public-locations]');
                const mobile = window.matchMedia('(max-width: 767px)');
                const hasErrors = @json($errors->any());
                const setFilters = expanded => {
                    shell.classList.toggle('is-filters-collapsed', !expanded);
                    panel.inert = !expanded;
                    toggle.setAttribute('aria-expanded', String(expanded));
                    toggle.firstElementChild.textContent = expanded ? '←' : '→';
                    label.textContent = expanded ? 'Hide filters' : 'Filters';
                    if (expanded && mobile.matches && locations) locations.open = false;
                };
                toggle.hidden = false;
                setFilters(!mobile.matches || hasErrors);
                toggle.addEventListener('click', () => setFilters(toggle.getAttribute('aria-expanded') !== 'true'));
                mobile.addEventListener('change', () => setFilters(!mobile.matches || hasErrors));
                locations?.addEventListener('toggle', () => {
                    locations.querySelector('summary').setAttribute('aria-expanded', String(locations.open));
                    if (locations.open && mobile.matches) setFilters(false);
                });
                root.addEventListener('keydown', event => {
                    if (event.key !== 'Escape') return;
                    if (locations?.open && locations.contains(document.activeElement)) {
                        locations.open = false;
                        locations.querySelector('summary').focus();
                    } else if (panel.contains(document.activeElement)) {
                        setFilters(false);
                        toggle.focus();
                    }
                });
                root.addEventListener('click', event => {
                    if (!mobile.matches || !event.target.closest('[data-viewer-select]')) return;
                    if (locations) locations.open = false;
                    root.querySelector('[data-viewer-map]')?.focus({ preventScroll: true });
                });
                // The existing map ResizeObserver handles sidebar size transitions.
            })();
        </script>
    </main>
    @else
    <main data-gis-viewer id="main-content" tabindex="-1" class="gis-page am-public-gis">
        <div class="am-gis-shell">
            <aside class="am-gis-filter-rail" aria-label="Map search and filters">
                <div class="am-gis-intro">
                    <h1>Association location map</h1>
                    <p>{{ $internal ? 'View locations within your current association access.' : 'Explore published association locations and program coverage.' }}</p>
                </div>
                <details class="am-gis-filter-toggle" data-public-filter-panel open>
                    <summary>Search and filters</summary>
                    <form method="get" class="am-gis-controls" aria-label="Filter locations">
                        <label>Search association or location<input class="gis-input" name="search" type="search" maxlength="200" value="{{ $filters['search'] ?? '' }}"></label>
                        <div class="am-gis-filter-fields">
                            @foreach(['municipality' => 'Municipality', 'barangay' => 'Barangay', 'component' => 'Program component', 'commodity' => 'Commodity'] as $field => $label)
                                <label>{{ $label }}<input class="gis-input" name="{{ $field }}" maxlength="255" placeholder="Exact name" value="{{ $filters[$field] ?? '' }}"></label>
                            @endforeach
                        </div>
                        <div class="am-gis-filter-actions">
                            <button class="gis-button gis-save-button" type="submit">Apply filters</button>
                            <a class="gis-button" href="{{ route($internal ? 'gis.viewer' : 'gis.public') }}">Clear filters</a>
                        </div>
                        <p class="mt-3 text-xs text-slate-600">Area, component and commodity filters match the full name.</p>
                        @if($errors->any())<p role="alert" class="mt-3 text-sm text-red-800">{{ $errors->first() }}</p>@endif
                    </form>
                </details>
            </aside>
            <div class="am-gis-map-area">
                @if($data === null)
                    <section role="alert" class="rounded-xl border border-amber-300 bg-white p-5"><h2 class="font-semibold">Locations are temporarily unavailable</h2><p class="mt-2">Please reload this page to try again.</p></section>
                @else
                    <section class="am-gis-map-panel" aria-label="Location map">
                        <p role="status" class="am-gis-count">{{ count($data['records']) }} {{ count($data['records']) === 1 ? 'location' : 'locations' }} on page {{ $data['page'] }}. {{ $internal ? 'Publication status is shown for each location.' : 'Only published locations are shown.' }}</p>
                        <p data-viewer-status role="status" class="text-slate-600">Loading map… The location list remains available.</p>
                        <div data-viewer-map class="gis-map" aria-label="Interactive association map"></div>
                        <p class="text-slate-600">Select a pin or a location in the list. Numbered pins group nearby locations. Coordinates use WGS84.</p>
                    </section>
                    <details class="am-gis-locations" aria-label="Location list">
                        <summary>Locations ({{ count($data['records']) }})</summary>
                        <div class="am-gis-location-list" tabindex="0" aria-label="Scrollable locations">
                        @forelse($data['records'] as $record)
                            <article class="break-words border-b border-slate-100 p-4">
                                <h3 class="font-semibold">{{ $record['name'] }}</h3>
                                <p class="mt-1 text-sm">{{ $record['association'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $record['barangay'] }} · {{ $record['municipality'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $record['component'] }}</p>
                                @if($record['project_title'])<p class="mt-2 text-sm"><span class="font-semibold">Project:</span> {{ $record['project_title'] }}</p>@endif
                                @if($record['commodity'])<p class="mt-1 text-sm"><span class="font-semibold">Commodity:</span> {{ $record['commodity'] }}</p>@endif
                                <p class="mt-1 text-xs text-slate-600">{{ $record['latitude'] }}, {{ $record['longitude'] }}</p>
                                @if($internal)<p class="mt-2 text-xs font-semibold">{{ $record['published'] ? 'Published' : 'Unpublished — internal' }}</p>@endif
                                <button data-viewer-select="{{ $loop->index }}" type="button" hidden class="gis-button mt-3">Show on map</button>
                            </article>
                        @empty
                            <p class="p-5 text-sm text-slate-600">No locations match these filters. Try clearing the filters.</p>
                        @endforelse
                        </div>
                    </details>
                @endif
            </div>
        </div>
        @if($data !== null)
            <nav class="am-gis-pages" aria-label="Location pages">
                @if($data['page'] > 1)<a class="gis-button" href="{{ request()->fullUrlWithQuery(['page' => $data['page'] - 1]) }}">Previous page</a>@endif
                @if($data['has_more'])<a class="gis-button" href="{{ request()->fullUrlWithQuery(['page' => $data['page'] + 1]) }}">Next page</a>@endif
            </nav>
            <script type="application/json" data-viewer-records>{!! json_encode($data['records'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        @endif
        <noscript><p>Enable JavaScript for the interactive map. Filters and location details work without it.</p></noscript>
        <script>
            (() => {
                const panel = document.querySelector('[data-public-filter-panel]');
                const mobile = window.matchMedia('(max-width: 767px)');
                const hasErrors = @json($errors->any());
                const syncFilters = () => { panel.open = !mobile.matches || hasErrors; };
                syncFilters();
                mobile.addEventListener('change', syncFilters);
            })();
        </script>
    </main>
    @endunless
</x-public-layout>
