@php
    $title = $location ? 'Location history' : 'Archived GIS locations';
    $pages = $location ? $events : $records;

    // Display only relevant GIS values, never an unrestricted audit JSON dump.
    $labels = [
        'association_id' => 'Association ID',
        'location_name' => 'Location name',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'project_id' => 'Project ID',
        'is_published' => 'Published',
        'archived_at' => 'Archived at',
        // These values come from recorded changes, not reconstructed history.
        'association_name' => 'Association name',
        'municipality_id' => 'City / Municipality ID',
        'barangay_id' => 'Barangay ID',
        'program_component_id' => 'Program component ID',
        'association_status_id' => 'Association status ID',
        'project_title' => 'Project title',
        'commodity' => 'Commodity',
        'project_archived' => 'Project archived',
        'association_name' => 'Association name',
        'municipality_id' => 'City / Municipality ID',
        'barangay_id' => 'Barangay ID',
        'program_component_id' => 'Program component ID',
        'association_status_id' => 'Association status ID',
        'project_title' => 'Project title',
        'commodity' => 'Commodity',
        'project_archived' => 'Project archived',
    ];

    $display = static function ($value): string {
        if ($value === null) return 'Not recorded';
        if (is_bool($value)) return $value ? 'Yes' : 'No';

        return is_scalar($value) ? (string) $value : 'Structured value';
    };
@endphp

<section data-gis-history-panel data-title="{{ $title }}">
    @if($location)
        <header class="gis-history-context">
            <h3>{{ $location->location_name }}</h3>
            <p>{{ $location->association?->name ?? 'Association unavailable' }}</p>
            <span class="gis-badge">
                {{ $location->archived_at ? 'Archived' : ($location->is_published ? 'Published' : 'Unpublished') }}
            </span>
            <p class="gis-history-muted">
                {{ $location->latitude }}, {{ $location->longitude }}
            </p>
        </header>

        <ol class="gis-history-timeline">
            @forelse($events as $event)
                @php
                    $decoded = json_decode($event->details ?? '', true);
                    $details = is_array($decoded) ? $decoded : [];
                    $before = is_array($details['before'] ?? null) ? $details['before'] : [];
                    $after = is_array($details['after'] ?? null) ? $details['after'] : [];
                @endphp

                <li>
                    <strong>{{ ucfirst(strtolower($event->action_type)) }}</strong>
                    <p class="gis-history-muted">
                        {{ $event->actor_name ?? 'Actor unavailable' }}
                        · {{ $event->performed_at ?? 'Date not recorded' }}
                    </p>

                    {{-- Display the reason stored directly in this audit event. --}}
                    @if(is_string($details['reason'] ?? null) && trim($details['reason']) !== '')
                        <p class="gis-history-muted">
                            <strong>Reason:</strong> {{ $details['reason'] }}
                        </p>
                    @endif

                    {{-- Some existing events store the reason at the top level;
                        direct GIS edits may store it inside the after values. --}}
                    @php
                        $recordedReason = $details['reason'] ?? $after['reason'] ?? null;
                    @endphp

                    @if(is_string($recordedReason) && trim($recordedReason) !== '')
                        <p class="gis-history-muted">{{ $recordedReason }}</p>
                    @endif

                    <dl class="gis-history-values">
                        @foreach($labels as $field => $label)
                            @if(array_key_exists($field, $before) || array_key_exists($field, $after))
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>
                                        @if(array_key_exists($field, $before))
                                            <span>{{ $display($before[$field]) }}</span>
                                            <span aria-label="changed to"> → </span>
                                        @endif
                                        <strong>
                                            {{ array_key_exists($field, $after)
                                                ? $display($after[$field])
                                                : 'Not recorded' }}
                                        </strong>
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    @if($before === [] && $after === [])
                        <p class="gis-history-muted">No structured before/after values recorded.</p>
                    @endif
                </li>
            @empty
                <li>No GIS audit events recorded for this location.</li>
            @endforelse
        </ol>
    @else
        <div class="gis-archive-list">
            @forelse($records as $record)
                <article class="gis-history-context">
                    <h3>{{ $record->location_name }}</h3>
                    <p>{{ $record->association?->name ?? 'Association unavailable' }}</p>
                    <p class="gis-history-muted">
                        Archived {{ $record->archived_at }}
                    </p>
                    <a class="gis-button" data-gis-history
                       href="{{ route($historyRoute, $record->id) }}">
                        View History
                    </a>
                </article>
            @empty
                <p>No archived GIS locations within your access scope.</p>
            @endforelse
        </div>
    @endif

    @if($pages->hasPages())
        <nav class="gis-history-actions" aria-label="History pagination">
            @if($pages->previousPageUrl())
                <a class="gis-button" data-gis-history
                   href="{{ $pages->previousPageUrl() }}">Previous</a>
            @endif
            <span>Page {{ $pages->currentPage() }} of {{ $pages->lastPage() }}</span>
            @if($pages->nextPageUrl())
                <a class="gis-button" data-gis-history
                   href="{{ $pages->nextPageUrl() }}">Next</a>
            @endif
        </nav>
    @endif
</section>