<x-dashboard-layout title="Assigned Association Details">
<div class="fo-areas">
    <a class="fo-action"
       href="{{ route('officer.areas.show', $association->area_unit_id) }}">
        Back to coverage
    </a>

    {{-- The drawer extracts only this section from the returned page. --}}
    <section data-area-drawer-content class="fo-stack">
        @php
            $parameters = [
                'areaUnit' => $association->area_unit_id,
                'association' => $association->id,
            ];
            $titles = [
                'association' => 'Association overview',
                'members' => 'Members',
                'projects' => 'Projects',
                'gis' => 'GIS locations',
            ];
        @endphp

        {{-- The shared header shows this section name after the introduction scrolls away. --}}
        <header class="am-drawer-intro"
                data-drawer-context="{{ $titles[$section] }}">
                <p class="fo-eyebrow">{{ $titles[$section] }}</p>
                <h3 class="fo-drawer-title">{{ $association->name }}</h3>
                <p class="fo-record-id">
                    ASSOC-{{ str_pad((string) $association->id, 6, '0', STR_PAD_LEFT) }}
                </p>
        </header>

        {{-- These links replace this drawer's content; they never stack dialogs. --}}
        <nav class="fo-drawer-tabs am-drawer-nav" aria-label="Association records">
            @foreach($titles as $key => $label)
                <a data-area-drawer
                   href="{{ route('officer.areas.details', $parameters + ['section' => $key]) }}"
                   @if($section === $key) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if($section === 'association')
            <dl class="fo-definition">
                @foreach([
                    'Municipality' => $association->areaUnit?->name,
                    'Barangay' => $association->subUnit?->name,
                    'Program component' => $association->programComponent?->name,
                    'Operational status' => $association->status?->status_name,
                    'Archive state' => $association->is_archived ? 'Archived' : 'Not archived',
                ] as $label => $value)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value ?? 'Not recorded' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if(!$association->subUnit
                || (int) $association->subUnit->area_unit_id !== (int) $association->area_unit_id)
                <div class="fo-warning">
                    <strong>Area information needs review</strong>
                    <p>The barangay is missing or belongs to another municipality.</p>
                </div>
            @endif

            <div class="grid grid-cols-3 gap-3">
                @foreach([
                    'members' => ['current_members_count', 'Members'],
                    'projects' => ['retained_projects_count', 'Projects'],
                    'gis' => ['locations_count', 'GIS locations'],
                ] as $key => [$field, $label])
                    <a data-area-drawer
                       href="{{ route('officer.areas.details', $parameters + ['section' => $key]) }}">
                        <strong data-count-up>{{ number_format($association->$field) }}</strong>
                        <span>{{ $label }} →</span>
                    </a>
                @endforeach
            </div>

            <section class="fo-card fo-card-padded">
                <h4 class="font-semibold">GIS mapping preview</h4>
                @forelse($locations as $location)
                    <div class="fo-list-record">
                        <strong>{{ $location->location_name ?: 'Unnamed location' }}</strong>
                        <span class="fo-record-id">
                            Latitude: {{ $location->latitude ?? 'Not recorded' }}<br>
                            Longitude: {{ $location->longitude ?? 'Not recorded' }}
                        </span>
                        <span class="fo-pill {{ $location->is_published
                            ? 'fo-pill-green' : 'fo-pill-amber' }}">
                            {{ $location->is_published ? 'Published' : 'Unpublished' }}
                        </span>
                    </div>
                @empty
                    <div class="fo-warning mt-3">
                        No non-archived GIS locations are recorded.
                    </div>
                @endforelse

                <a data-area-drawer class="fo-action"
                   href="{{ route('officer.areas.details', $parameters + ['section' => 'gis']) }}">
                    View all {{ $association->locations_count }} GIS locations →
                </a>
            </section>

            <a class="fo-action fo-primary"
               href="{{ route('officer.associations.show', $association->id) }}">
                Open complete association page
            </a>
        @else
            <div class="fo-section-heading">
                <strong>{{ $records->total() }} {{ strtolower($titles[$section]) }}</strong>
                <span class="fo-pill fo-pill-slate">Non-archived records</span>
            </div>

            <div>
                @forelse($records as $record)
                    <article class="fo-card fo-card-padded mb-3">
                        @if($section === 'members')
                            <p class="fo-record-id">
                                MEMBER-{{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}
                            </p>
                            <h4 class="fo-record-title">
                                {{ $record->first_name }} {{ $record->last_name }}
                            </h4>
                            <dl class="fo-definition">
                                <div>
                                    <dt>Association role</dt>
                                    <dd>{{ $record->role_in_assoc ?: 'Not recorded' }}</dd>
                                </div>
                                <div>
                                    <dt>Registered</dt>
                                    <dd>{{ $record->date_registered?->format('M d, Y') ?? 'Not recorded' }}</dd>
                                </div>
                            </dl>
                            <a class="fo-action"
                               href="{{ route('membership.members.show', $record->id) }}">
                                Open member page →
                            </a>

                        @elseif($section === 'projects')
                            <p class="fo-record-id">
                                PROJECT-{{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}
                            </p>
                            <h4 class="fo-record-title">{{ $record->title }}</h4>
                            <dl class="fo-definition">
                                <div>
                                    <dt>Status</dt>
                                    <dd>{{ $record->status?->status_name ?? 'Not recorded' }}</dd>
                                </div>
                                <div>
                                    <dt>Commodity</dt>
                                    <dd>{{ $record->commodity_type ?: 'Not recorded' }}</dd>
                                </div>
                                <div>
                                    <dt>Implementation date</dt>
                                    <dd>{{ $record->implementation_date?->format('M d, Y') ?? 'Not recorded' }}</dd>
                                </div>
                            </dl>
                            <a class="fo-action"
                               href="{{ route('officer.projects.show', $record->id) }}">
                                Open project page →
                            </a>

                        @else
                            <p class="fo-record-id">LOCATION-{{ $record->id }}</p>
                            <h4 class="fo-record-title">
                                {{ $record->location_name ?: 'Unnamed location' }}
                            </h4>
                            <dl class="fo-definition">
                                <div><dt>Latitude</dt><dd>{{ $record->latitude ?? 'Not recorded' }}</dd></div>
                                <div><dt>Longitude</dt><dd>{{ $record->longitude ?? 'Not recorded' }}</dd></div>
                            </dl>
                            <span class="fo-pill {{ $record->is_published
                                ? 'fo-pill-green' : 'fo-pill-amber' }}">
                                {{ $record->is_published ? 'Published' : 'Unpublished' }}
                            </span>
                        @endif
                    </article>
                @empty
                    <div class="fo-warning">
                        No non-archived {{ strtolower($titles[$section]) }} are recorded.
                    </div>
                @endforelse
            </div>

            <div data-drawer-pagination>
                <x-management-pagination :records="$records"
                                         label="Association details pagination"/>
            </div>
        @endif

        @if(in_array($section, ['association', 'gis'], true))
            {{-- The existing GIS page has no verified association deep-link filter. --}}
            <a class="fo-action" href="{{ route('gis.officer.index') }}">
                Open GIS Mapping →
            </a>
        @endif
    </section>
</div>
</x-dashboard-layout>