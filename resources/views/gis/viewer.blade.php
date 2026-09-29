<x-app-layout title="Association location map">
    <main data-gis-viewer class="gis-page mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">BFAR SAAD Phase II · Cebu Province</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">Association location map</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $internal ? 'View locations within your current association access.' : 'Explore published association locations and program coverage.' }}</p>
            </div>
            <nav aria-label="Map navigation" class="flex flex-wrap gap-3">
                <a class="gis-button" href="{{ route('home') }}">Home</a>
                <a class="gis-button" href="{{ route($internal ? 'gis.public' : 'login') }}">{{ $internal ? 'Public map' : 'Staff sign in' }}</a>
            </nav>
        </header>
        <form method="get" class="rounded-xl border border-slate-200 bg-white p-4" aria-label="Filter locations">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-medium">Search association or location<input class="gis-input" name="search" type="search" maxlength="200" value="{{ $filters['search'] ?? '' }}"></label>
                @foreach(['municipality' => 'Municipality', 'barangay' => 'Barangay', 'component' => 'Program component', 'commodity' => 'Commodity'] as $field => $label)
                    <label class="text-sm font-medium">{{ $label }}<input class="gis-input" name="{{ $field }}" maxlength="255" placeholder="Exact name" value="{{ $filters[$field] ?? '' }}"></label>
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button class="gis-button gis-save-button" type="submit">Apply filters</button>
                <a class="gis-button" href="{{ route($internal ? 'gis.viewer' : 'gis.public') }}">Clear filters</a>
                <span class="text-xs text-slate-600">Area, component and commodity filters match the full name.</span>
            </div>
            @if($errors->any())<p role="alert" class="mt-3 text-sm text-red-800">{{ $errors->first() }}</p>@endif
        </form>
        @if($data === null)
            <section role="alert" class="rounded-xl border border-amber-300 bg-white p-5"><h2 class="font-semibold">Locations are temporarily unavailable</h2><p class="mt-2">Please reload this page to try again.</p></section>
        @else
            <p role="status" class="text-sm text-slate-600">{{ count($data['records']) }} {{ count($data['records']) === 1 ? 'location' : 'locations' }} on page {{ $data['page'] }}. {{ $internal ? 'Publication status is shown for each location.' : 'Only published locations are shown.' }}</p>
            <div class="grid gap-4 lg:grid-cols-3">
                <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white lg:col-span-2" aria-label="Location map">
                    <p data-viewer-status role="status" class="p-3 text-sm text-slate-600">Loading map… The location list remains available.</p>
                    <div data-viewer-map class="gis-map" aria-label="Interactive association map"></div>
                    <p class="p-3 text-xs text-slate-600">Select a pin or a location in the list. Numbered pins group nearby locations. Coordinates use WGS84.</p>
                </section>
                <section class="min-w-0 rounded-xl border border-slate-200 bg-white" aria-label="Location list">
                    <h2 class="border-b border-slate-200 p-4 font-semibold">Locations</h2>
                    <div class="max-h-[650px] overflow-y-auto">
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
                </section>
            </div>
            <nav class="flex gap-3" aria-label="Location pages">
                @if($data['page'] > 1)<a class="gis-button" href="{{ request()->fullUrlWithQuery(['page' => $data['page'] - 1]) }}">Previous page</a>@endif
                @if($data['has_more'])<a class="gis-button" href="{{ request()->fullUrlWithQuery(['page' => $data['page'] + 1]) }}">Next page</a>@endif
            </nav>
            <script type="application/json" data-viewer-records>{!! json_encode($data['records'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        @endif
        <noscript><p>Enable JavaScript for the interactive map. Filters and location details work without it.</p></noscript>
        <footer class="border-t border-slate-200 pt-4 text-xs text-slate-600">Department of Agriculture · Bureau of Fisheries and Aquatic Resources, Region VII</footer>
    </main>
</x-app-layout>
