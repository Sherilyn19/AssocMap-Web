<x-dashboard-layout title="Field Officer Dashboard" topbar-title="Field workspace">
<div class="officer-dashboard">
    <header class="od-welcome">
        <div>
            <span class="od-eyebrow">BFAR SAAD Phase II · Field operations</span>
            <h1>Welcome, {{ $actor->name }}</h1>
            <p>Your assigned communities, connected in one place.</p>
        </div>

        <div>
            <p class="od-access">Field Officer · Active</p>
            <nav class="od-actions" aria-label="Quick actions">
                <a class="od-button od-primary"
                   href="{{ route('monitoring.create', 'production') }}">
                    Record production <span aria-hidden="true">↗</span>
                </a>
                <a class="od-button" href="{{ route('officer.reports.index') }}">
                    My Reports
                </a>
            </nav>
        </div>
    </header>

    <section class="od-metrics" aria-label="Current assignment summary">
        @foreach ([
            ['Assigned areas', $areas->count(), 'Cities / Municipalities in your coverage', 'officer.areas.index', 'M12 21s7-6 7-11a7 7 0 0 0-14 0c0 5 7 11 7 11ZM9 10a3 3 0 1 0 6 0 3 3 0 0 0-6 0'],
            ['Assigned associations', $counts['My Associations'], 'Current association records', 'officer.associations.index', 'M4 21V10l8-6 8 6v11M9 21v-6h6v6'],
            ['Members', $counts['My Members'], 'Non-archived member records', 'membership.index', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-4'],
            ['Pending applications', $pendingApplications, 'Awaiting Field Officer review', 'membership.index', 'M9 3h6v4H9zM9 5H5v16h14V5h-4M8 12h8M8 16h5']
        ] as [$label, $value, $context, $destination, $icon])
            <a class="od-metric" href="{{ route($destination) }}">
                <div class="od-metric-heading">
                    <h2>{{ $label }}</h2>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $icon }}"/>
                    </svg>
                </div>
                <strong data-count-up>{{ number_format($value) }}</strong>
                <p>{{ $context }}</p>
            </a>
        @endforeach
    </section>

    <section class="od-panel" aria-labelledby="coverage-heading" data-gis-viewer>
        <div class="od-heading">
            <div>
                <span class="od-eyebrow">01 / Geographic coverage</span>
                <h2 id="coverage-heading">Your field, in focus</h2>
                <p>Saved locations for your current assigned associations.</p>
            </div>
            <a class="od-button" href="{{ route('gis.officer.index') }}">
                Open GIS Mapping <span aria-hidden="true">↗</span>
            </a>
        </div>

        <div class="od-coverage">
            <div class="od-map-panel">
                <div class="od-map-caption">
                    <span>Association locations</span>
                    <span>{{ count($mapData['records']) }} in this preview</span>
                </div>

                <div data-viewer-map class="od-map"
                     aria-label="Interactive map of assigned association locations"></div>

                <p data-viewer-status role="status" class="od-map-status">
                    Loading map… Location details remain available below.
                </p>

                @if (count($mapData['records']) === 0)
                    <p class="od-empty">
                        No valid saved locations yet. The map shows a general view
                        without association pins.
                    </p>
                @endif

                @if ($mapData['has_more'])
                    <p class="od-empty">
                        Showing the first 200 locations. Open GIS Mapping to
                        explore all assigned locations.
                    </p>
                @endif

                <noscript>
                    <p class="od-empty">
                        Enable JavaScript for the map. Area and association
                        details remain available.
                    </p>
                </noscript>
            </div>

            <aside class="od-areas" aria-labelledby="areas-heading">
                <div class="od-area-heading">
                    <h3 id="areas-heading">Assigned areas</h3>
                    <span>{{ $areas->count() }}</span>
                </div>
                <p class="od-muted">Coverage through your current associations</p>

                <div class="od-area-list">
                    @forelse ($areas as $area)
                        <a class="od-area"
                           href="{{ route('officer.associations.index', ['area_unit_id' => $area->id]) }}">
                            <span>
                                <strong>{{ $area->name }}</strong>
                                <small>
                                    {{ number_format($area->associations_count) }}
                                    assigned {{ $area->associations_count === 1 ? 'association' : 'associations' }}
                                </small>
                            </span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    @empty
                        <p class="od-empty">No areas linked to your current assignments.</p>
                    @endforelse
                </div>

                <details class="od-locations">
                    <summary>Locations in this preview ({{ count($mapData['records']) }})</summary>
                    <div class="od-location-list">
                        @forelse ($mapData['records'] as $record)
                            <article>
                                <strong>{{ $record['name'] }}</strong>
                                <p>{{ $record['association'] }}</p>
                                <p>{{ $record['barangay'] }} · {{ $record['municipality'] }}</p>
                                <small>{{ $record['published'] ? 'Published' : 'Unpublished · Internal' }}</small>
                                <button type="button" hidden
                                        data-viewer-select="{{ $loop->index }}"
                                        class="od-button">
                                    Show on map
                                </button>
                            </article>
                        @empty
                            <p class="od-empty">No mapped locations to display.</p>
                        @endforelse
                    </div>
                </details>

                <p class="od-note">
                    Only your assigned associations are included.
                    Unpublished locations remain internal.
                </p>
            </aside>
        </div>

        <script type="application/json" data-viewer-records>{!! json_encode($mapData['records'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    </section>

    <section class="od-panel od-register" aria-labelledby="associations-heading">
        <div class="od-heading">
            <div>
                <span class="od-eyebrow">02 / Community register</span>
                <h2 id="associations-heading">Assigned associations</h2>
                <p>The people and programs within your coverage.</p>
            </div>
            <a class="od-link" href="{{ route('officer.associations.index') }}">
                View all associations →
            </a>
        </div>

        @include('field-officer-user.associations.table')

        <p class="od-panel-footer">
            Showing {{ $associations->count() }} of
            {{ number_format($counts['My Associations']) }} current assigned associations.
        </p>
    </section>

    <section aria-labelledby="activity-heading">
        <div class="od-activity-heading">
            <span class="od-eyebrow">03 / Program activity</span>
            <h2 id="activity-heading">Progress on the ground</h2>
        </div>

        <nav class="od-programs" aria-label="Program records">
            @foreach ([
                [$projectCount, 'Projects & Delivery', 'officer.projects.index'],
                [$counts['Monitoring Records'], 'Monitoring records', 'monitoring.index'],
                [$counts['Training Records'], 'Training records', 'officer.trainings.index']
            ] as [$value, $label, $destination])
                <a href="{{ route($destination) }}">
                    <strong>{{ number_format($value) }}</strong>
                    <span>{{ $label }}</span>
                    <span aria-hidden="true">↗</span>
                </a>
            @endforeach
        </nav>

        <div class="od-activity-grid">
            <section class="od-panel" aria-labelledby="production-heading">
                <div class="od-heading">
                    <div>
                        <h3 id="production-heading">Recent production monitoring</h3>
                        <p>Latest updated records · Actual against target</p>
                    </div>
                </div>

                <div class="od-records">
                    @forelse ($recent as $record)
                        <article class="od-production">
                            <div class="od-record-heading">
                                <a href="{{ route('officer.projects.show', $record->project_id) }}">
                                    {{ $record->project_title }}
                                </a>
                                <small>{{ $record->quarter_name }} / {{ $record->year }}</small>
                            </div>
                            <p>{{ $record->association_name }}</p>
                            <div class="od-achievement">
                                <span>Achievement</span>
                                <strong>
                                    {{ \App\Support\MonitoringProgress::label($record->target_output, $record->actual_output) }}
                                </strong>
                            </div>

                            @if (is_numeric($record->target_output) && $record->target_output > 0 && is_numeric($record->actual_output) && $record->actual_output >= 0)
                                <progress max="100"
                                          value="{{ min(100, $record->actual_output / $record->target_output * 100) }}"
                                          aria-label="{{ $record->project_title }} target achievement">
                                    {{ \App\Support\MonitoringProgress::label($record->target_output, $record->actual_output) }}
                                </progress>
                            @endif
                        </article>
                    @empty
                        <p class="od-empty">
                            No production monitoring records yet.
                            Recorded progress will appear here.
                        </p>
                    @endforelse
                </div>

                <a class="od-panel-footer od-link" href="{{ route('monitoring.index') }}">
                    View monitoring records →
                </a>
            </section>

            <section class="od-panel" aria-labelledby="training-heading">
                <div class="od-heading">
                    <div>
                        <h3 id="training-heading">Training updates</h3>
                        <p>Latest updated training records</p>
                    </div>
                </div>

                <div class="od-timeline">
                    @forelse ($trainings as $training)
                        <article>
                            <span class="od-timeline-dot" aria-hidden="true"></span>
                            <div>
                                <a href="{{ route('officer.trainings.show', $training) }}">
                                    {{ $training->title }}
                                </a>
                                <p>{{ $training->association?->name }}</p>
                                <small>
                                    Training date:
                                    {{ $training->date_conducted?->format('M d, Y') ?? 'Not scheduled' }}
                                </small>
                                <small>{{ $training->venue ?: 'Venue not recorded' }}</small>
                            </div>
                        </article>
                    @empty
                        <p class="od-empty">No training records for your current assignments.</p>
                    @endforelse
                </div>

                <a class="od-panel-footer od-link"
                   href="{{ route('officer.trainings.index') }}">
                    View training records →
                </a>
            </section>
        </div>
    </section>

    <footer class="od-footer">
        Department of Agriculture · Bureau of Fisheries and Aquatic Resources
        · SAAD Phase II
    </footer>
</div>
</x-dashboard-layout>