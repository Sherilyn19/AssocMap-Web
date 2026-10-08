{{-- FO-only presentation. Laravel supplies records within the current assignment. --}}
<div data-gis-page data-fo-gis class="gis-page fo-gis">
    @include('shared.gis.publication')

    <header class="fo-gis-header">
        <div>
            <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
            <h1>GIS Mapping</h1>
            <p>View and manage locations within your assigned associations.</p>
        </div>

        <div class="fo-gis-actions">
            <a class="gis-button"
               href="{{ route('gis.public') }}"
               target="_blank" rel="noopener">
                Public GIS Map ↗
            </a>

            <button data-gis-add hidden type="button"
                    class="gis-button gis-save-button">
                + Add location
            </button>
        </div>
    </header>

    <p data-gis-feedback hidden role="status" class="fo-gis-feedback"></p>

    {{-- Cards show overall assignment totals, independent of the current filters. --}}
    <section class="fo-gis-cards" aria-label="Assigned GIS summary">
        @foreach([
            'current' => ['Current locations', 'All current records', ''],
            'visible' => ['Publicly visible', 'Eligible published locations', 'public'],
            'hidden' => ['Not publicly visible', 'Unpublished or ineligible', 'hidden'],
        ] as $key => [$label, $description, $visibility])
            <button type="button"
                    data-gis-card="{{ $visibility }}"
                    class="fo-gis-card fo-gis-card-{{ $key }}">
                <span>{{ $label }}</span>
                <strong>{{ number_format($summary[$key]) }}</strong>
                <small>{{ $description }} <span aria-hidden="true">→</span></small>
            </button>
        @endforeach

        <a class="fo-gis-card fo-gis-card-archived"
           data-gis-history
           href="{{ route('gis.officer.archived') }}">
            <span>Archived locations</span>
            <strong>{{ number_format($summary['archived']) }}</strong>
            <small>Read-only records and history <span aria-hidden="true">→</span></small>
        </a>
    </section>

    <section data-gis-controls hidden class="fo-gis-filter">
        <header class="fo-gis-section-heading">
            <h2>Filter locations</h2>
            <span class="fo-gis-scope">Your assignments only</span>
        </header>

        <form data-fo-gis-filters>
            <div class="fo-gis-filter-grid">
                <label>
                    Search
                    <input data-filter="search" type="search" maxlength="200"
                           class="gis-input" placeholder="Location or association">
                </label>

                <label>
                    Association
                    <select data-filter="association" class="gis-input">
                        <option value="">All assigned associations</option>
                        @foreach($associations as $association)
                            <option value="{{ $association->id }}">
                                {{ $association->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Public visibility
                    <select data-filter="visibility" class="gis-input">
                        <option value="">All current locations</option>
                        <option value="public">Publicly visible</option>
                        <option value="hidden">Not publicly visible</option>
                    </select>
                </label>

                <label>
                    Municipality
                    <select data-filter="municipality" class="gis-input">
                        <option value="">All municipalities</option>
                    </select>
                </label>

                <label>
                    Barangay
                    <select data-filter="barangay" class="gis-input">
                        <option value="">All barangays</option>
                    </select>
                </label>

                <label>
                    Program component
                    <select data-filter="component" class="gis-input">
                        <option value="">All components</option>
                    </select>
                </label>

                <label>
                    Commodity
                    <select data-filter="commodity" class="gis-input">
                        <option value="">All commodities</option>
                    </select>
                </label>
            </div>

            <footer class="fo-gis-filter-footer">
                <p data-gis-summary role="status" aria-live="polite">
                    {{ $records->count() }} current locations
                </p>
                <div class="fo-gis-actions">
                    <button data-gis-clear type="button" class="gis-button">
                        Reset
                    </button>
                    <button data-gis-apply type="submit"
                            class="gis-button gis-save-button">
                        Apply filters
                    </button>
                </div>
            </footer>
        </form>
    </section>

    <noscript>
        <p>Enable JavaScript to use map selection, filters, and location editing.</p>
    </noscript>

    <div class="gis-workspace">
        <section class="gis-map-card">
            <header class="fo-gis-section-heading">
                <div class="fo-gis-actions">
                    <h2>Internal GIS Map</h2>
                    <x-info label="Internal map information">
                        This map includes current locations within your assigned
                        associations. Green pins are publicly visible; amber pins
                        are not publicly visible. Numbered pins group nearby locations.
                    </x-info>
                </div>
                <button data-gis-reset type="button" disabled class="gis-button">
                    Reset view
                </button>
            </header>

            <p data-gis-map-status role="status" class="fo-gis-map-status">
                Loading map…
            </p>

            <div data-gis-map class="gis-map"
                 aria-label="Assigned association locations"></div>

            <footer class="fo-gis-legend">
                <span>
                    <span class="gis-legend-dot gis-published" aria-hidden="true"></span>
                    Publicly visible
                </span>
                <span>
                    <span class="gis-legend-dot gis-unpublished" aria-hidden="true"></span>
                    Not publicly visible
                </span>
            </footer>
        </section>

        <aside class="gis-record-panel" aria-label="Location records and editor">
            {{-- The editor replaces the list while leaving the map usable. --}}
            @include('shared.gis.form')

            <header data-gis-record-heading class="fo-gis-section-heading">
                <h2>Location records</h2>
                <span data-gis-list-count>{{ $records->count() }}</span>
            </header>

            <div class="gis-record-list">
                <p data-gis-empty hidden class="fo-gis-empty">
                    No locations match these filters.
                </p>

                @foreach($records as $record)
                    <article data-gis-row="{{ $record['id'] }}" class="gis-record">
                        <button data-gis-select="{{ $record['id'] }}"
                                type="button" disabled aria-pressed="false"
                                class="gis-record-button">
                            {{ $record['name'] }}
                            <span aria-hidden="true">↗</span>
                        </button>

                        <p>{{ $record['association'] }}</p>
                        <small>{{ $record['barangay'] }} · {{ $record['municipality'] }}</small>

                        <div class="fo-gis-record-footer">
                            <span class="gis-badge {{ $record['public_visible']
                                ? 'gis-badge-published'
                                : 'gis-badge-unpublished' }}">
                                {{ $record['public_visible']
                                    ? 'Publicly visible'
                                    : 'Not publicly visible' }}
                            </span>

                            <a data-gis-history class="fo-gis-history-link"
                               href="{{ $record['history_url'] }}">
                                History
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </aside>
    </div>

    <details class="fo-gis-unmapped">
        <summary>
            Assigned associations without a current location:
            {{ $unmapped->count() }}
        </summary>
        <ul>
            @forelse($unmapped as $association)
                <li>{{ $association->name }}</li>
            @empty
                <li>Every current assigned association has a location record.</li>
            @endforelse
        </ul>
    </details>

    {{-- Native dialog provides focus containment and keyboard support. --}}
    <dialog data-fo-gis-details class="fo-gis-dialog"
            aria-labelledby="fo-gis-details-title">
        <header class="fo-gis-dialog-heading">
            <div>
                <small>BFAR SAAD PHASE II / GIS</small>
                <h2 id="fo-gis-details-title">Location details</h2>
            </div>
            <button data-fo-gis-close type="button" class="gis-button">
                Close ×
            </button>
        </header>

        <div data-fo-gis-detail-content class="fo-gis-dialog-body"></div>
        <footer data-fo-gis-detail-actions class="fo-gis-dialog-footer"></footer>
    </dialog>

    {{-- Escape database content before embedding it in the page. --}}
    <script data-gis-data type="application/json">{!!
        json_encode(
            $records->values(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        )
    !!}</script>
</div>