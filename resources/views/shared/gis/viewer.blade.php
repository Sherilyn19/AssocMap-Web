<x-app-layout title="Association location map">
    @php
        $options = $options ?? [];
        $mapRoute = $internal ? 'gis.viewer' : 'gis.public';
    @endphp

    <main data-gis-viewer class="gis-page gis-explorer">
        <header class="gis-explorer-header">
            <div>
                <span class="gis-explorer-eyebrow">BFAR SAAD PHASE II · CEBU</span>
                <h1>{{ $internal ? 'Internal GIS Map' : 'Explore association locations' }}</h1>
                <p>
                    {{ $internal
                        ? 'Locations available within your current access.'
                        : 'Discover published association sites and livelihood projects.' }}
                </p>
            </div>

            <nav aria-label="Map navigation">
                <a class="gis-button" href="{{ route('home') }}">Home</a>
                <a class="gis-button"
                   href="{{ route($internal ? 'gis.public' : 'login') }}">
                    {{ $internal ? 'Public GIS Map' : 'Staff sign in' }}
                </a>
            </nav>
        </header>

        <form data-viewer-filter-form method="GET"
              action="{{ route($mapRoute) }}" class="gis-explorer-filters">
            <label class="gis-explorer-search">
                Search locations
                <input class="gis-input" name="search" type="search"
                       maxlength="200" placeholder="Association or location"
                       value="{{ $filters['search'] ?? '' }}">
            </label>

            @foreach([
                'municipality' => 'Municipality',
                'barangay' => 'Barangay',
                'component' => 'Program component',
                'commodity' => 'Commodity',
            ] as $field => $label)
                <label>
                    {{ $label }}
                    <select class="gis-input" name="{{ $field }}">
                        <option value="">All</option>

                        {{-- Preserve a submitted selection even if its records changed. --}}
                        @if(
                            !empty($filters[$field])
                            && !in_array($filters[$field], $options[$field] ?? [], true)
                        )
                            <option value="{{ $filters[$field] }}" selected>
                                {{ $filters[$field] }}
                            </option>
                        @endif

                        @foreach($options[$field] ?? [] as $option)
                            <option value="{{ $option }}"
                                    @selected(($filters[$field] ?? '') === $option)>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endforeach

            <div class="gis-explorer-filter-actions">
                <button type="submit" class="gis-button gis-save-button">
                    Find locations
                </button>
                <a class="gis-button" href="{{ route($mapRoute) }}">Reset</a>
            </div>

            <p data-viewer-filter-loading hidden role="status">
                Loading matching locations…
            </p>

            @if($errors->any())
                <p role="alert">{{ $errors->first() }}</p>
            @endif
        </form>

        @if($data === null)
            <section role="alert" class="gis-explorer-empty">
                <h2>Locations are temporarily unavailable</h2>
                <p>Please reload this page to try again.</p>
                <a class="gis-button" href="{{ request()->fullUrl() }}">Retry</a>
            </section>
        @else
            <div class="gis-explorer-result-heading">
                <p>
                    <strong>{{ count($data['records']) }}</strong>
                    locations on page {{ $data['page'] }}
                </p>
                <span>{{ $internal ? 'Authorized internal view' : 'Public locations only' }}</span>
            </div>

            <div class="gis-explorer-workspace">
                <section class="gis-explorer-map-card" aria-label="Association map">
                    <header>
                        <h2>{{ $internal ? 'Internal GIS Map' : 'Public GIS Map' }}</h2>
                        <button data-viewer-reset type="button"
                                class="gis-button" disabled>Reset view</button>
                    </header>

                    <p data-viewer-status role="status" class="gis-explorer-status">
                        Preparing interactive map…
                    </p>

                    <div data-viewer-map class="gis-map"></div>

                    <footer>
                        Select a pin or a location. Numbered pins group nearby sites.
                    </footer>
                </section>

                <section class="gis-explorer-results" aria-label="Matching locations">
                    <header><h2>Explore locations</h2></header>

                    <div class="gis-explorer-list">
                        @forelse($data['records'] as $record)
                            <article data-viewer-row="{{ $loop->index }}">
                                <span class="gis-explorer-component">
                                    {{ $record['component'] }}
                                </span>
                                <h3>{{ $record['name'] }}</h3>
                                <p>{{ $record['association'] }}</p>
                                <small>
                                    {{ $record['barangay'] }} · {{ $record['municipality'] }}
                                </small>

                                @if($record['commodity'])
                                    <p class="gis-explorer-commodity">{{ $record['commodity'] }}</p>
                                @endif

                                <button data-viewer-select="{{ $loop->index }}"
                                        type="button" hidden class="gis-button">
                                    View location →
                                </button>
                            </article>
                        @empty
                            <div class="gis-explorer-empty">
                                <h3>No matching locations</h3>
                                <p>Try another selection or reset the filters.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            <nav class="gis-explorer-pages" aria-label="Location pages">
                @if($data['page'] > 1)
                    <a class="gis-button"
                       href="{{ request()->fullUrlWithQuery(['page' => $data['page'] - 1]) }}">
                        Previous
                    </a>
                @endif

                <span>Page {{ $data['page'] }}</span>

                @if($data['has_more'])
                    <a class="gis-button"
                       href="{{ request()->fullUrlWithQuery(['page' => $data['page'] + 1]) }}">
                        Next
                    </a>
                @endif
            </nav>

            <dialog data-viewer-details class="gis-explorer-dialog"
                    aria-labelledby="viewer-details-title">
                <header>
                    <h2 id="viewer-details-title">Location details</h2>
                    <button type="button" data-viewer-close class="gis-button">Close ×</button>
                </header>
                <div data-viewer-detail-content></div>
            </dialog>

            <script type="application/json" data-viewer-records>{!!
                json_encode(
                    $data['records'],
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                        | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
                )
            !!}</script>
        @endif

        <noscript>
            <p>Enable JavaScript for interactive map selection. Search and filters remain available.</p>
        </noscript>

        <footer class="gis-explorer-footer">
            Department of Agriculture · Bureau of Fisheries and Aquatic Resources · Region VII
        </footer>
    </main>
</x-app-layout>