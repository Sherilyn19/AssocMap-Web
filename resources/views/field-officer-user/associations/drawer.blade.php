@php
    $titles = [
        'address' => 'Address and GIS locations',
        'officer' => 'Assigned officer',
        'members' => 'Official members',
        'projects' => 'Projects',
        'trainings' => 'Training records',
    ];
@endphp

<x-dashboard-layout :title="$titles[$section]">
<div class="fo-coverage space-y-5">
    <a href="{{ route('officer.associations.show', $association->id) }}"
       class="fo-action">
        Back to association
    </a>

    {{-- Reuse the fragment marker understood by the existing drawer script. --}}
    <section data-area-drawer-content class="fo-stack">
        <header class="am-drawer-intro">
            <p class="fo-eyebrow">{{ $titles[$section] }}</p>
            <h3 class="fo-drawer-title">{{ $association->name }}</h3>
            <p class="fo-record-id">
                ASSOC-{{ str_pad((string) $association->id, 6, '0', STR_PAD_LEFT) }}
            </p>
        </header>

        {{-- These links replace this panel's content instead of stacking modals. --}}
        <nav class="fo-drawer-tabs am-drawer-nav" aria-label="Association details">
            @foreach($titles as $key => $label)
                <a data-area-drawer
                   href="{{ route('officer.associations.details', [
                       'association' => $association->id,
                       'section' => $key,
                   ]) }}"
                   @if($section === $key) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if($section === 'address')
            <section class="fo-card fo-card-padded">
                <h4 class="font-semibold text-teal-800">Registered location</h4>
                <dl class="fo-definition mt-4">
                    @foreach([
                        'Address' => $association->address,
                        'City / Municipality' => $association->areaUnit?->name,
                        'Barangay' => $association->subUnit?->name,
                    ] as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ filled($value) ? $value : 'Not recorded' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @elseif($section === 'officer')
            <section class="fo-card fo-card-padded">
                @if($association->fieldOfficer)
                    {{-- Display professional account information only. --}}
                    <p class="fo-eyebrow">Current assignment</p>
                    <h4 class="fo-record-title">
                        {{ $association->fieldOfficer->name }}
                    </h4>
                    <dl class="fo-definition mt-4">
                        <div>
                            <dt>Email</dt>
                            <dd>{{ $association->fieldOfficer->email ?: 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt>Account state</dt>
                            <dd>
                                <span class="fo-pill {{ $association->fieldOfficer->is_active
                                    ? 'fo-pill-green' : 'fo-pill-amber' }}">
                                    {{ $association->fieldOfficer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Association</dt>
                            <dd>{{ $association->name }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="fo-warning">No assigned officer is recorded.</p>
                @endif
            </section>
        @endif

        @if($records !== null)
            <div class="fo-section-heading">
                <h4 class="font-semibold">
                    {{ number_format($records->total()) }}
                    {{ $section === 'address' ? 'GIS locations' : strtolower($titles[$section]) }}
                </h4>
                <span class="fo-pill fo-pill-slate">
                    {{ $publicOnly
                    ? 'Publicly visible locations'
                    : ($includeArchived ? 'Current and archived records' : 'Current records only') }}
                </span>
            </div>

            @if(!$publicOnly)

                <form method="GET"
                    action="{{ route('officer.associations.details', [
                        'association' => $association->id,
                        'section' => $section,
                    ]) }}"
                    class="fo-overview-filters"
                    data-record-filter>
                    <label class="fo-overview-check">
                        <input type="checkbox" name="include_archived" value="1"
                            @checked($includeArchived)>
                        Include archived records
                    </label>

                    <button type="submit" class="fo-action">Apply</button>
                </form>

            @endif

            @forelse($records as $item)
                @php
                    // All values below come from the authorized related records.
                    $title = match ($section) {
                        'address' => $item->location_name ?: 'Unnamed location',
                        'members' => implode(' ', array_filter(
                            [$item->first_name, $item->middle_name, $item->last_name],
                            fn ($part) => filled($part)
                        )),
                        default => $item->title,
                    };

                    $prefix = match ($section) {
                        'address' => 'LOCATION',
                        'members' => 'MEMBER',
                        'projects' => 'PROJECT',
                        default => 'TRAINING',
                    };

                    $fields = match ($section) {
                        'address' => [
                            'Latitude' => $item->latitude,
                            'Longitude' => $item->longitude,
                            // This describes the setting, not confirmed public-map eligibility.
                            'Publishing setting' => $item->is_published ? 'Enabled' : 'Disabled',
                        ],
                        'members' => [
                            'Association role' => $item->role_in_assoc,
                            'Representative' => (int) $association->representative_member_id === (int) $item->id
                                ? 'Yes' : 'No',
                            'Registered' => $item->date_registered?->format('M d, Y'),
                            'Beneficiary type' => $item->beneficiary_type,
                            'Contact number' => $item->contact_number,
                        ],
                        'projects' => [
                            'Status' => $item->status?->status_name,
                            'Commodity' => $item->commodity_type,
                            'Implementation date' => $item->implementation_date?->format('M d, Y'),
                            'Remarks' => $item->remarks,
                        ],
                        default => [
                            'Type' => $item->training_type,
                            'Venue' => $item->venue,
                            'Start date' => $item->date_conducted?->format('M d, Y'),
                            'End date' => $item->end_date?->format('M d, Y'),
                            'Conducted by' => $item->conducted_by,
                            'Remarks' => $item->remarks,
                        ],
                    };
                @endphp

                <article class="fo-card fo-card-padded">
                    <p class="fo-record-id">{{ $prefix }}-{{ $item->id }}</p>
                    @php
                        $archived = $section === 'address'
                            ? $item->archived_at !== null
                            : (bool) $item->is_archived;

                        // Archived locations use their existing read-only history.
                        // Current locations open the authorized internal GIS workspace.
                        $recordUrl = match ($section) {
                            'members' => route('membership.members.show', $item->id),
                            'projects' => route('officer.projects.show', $item->id),
                            'trainings' => route('officer.trainings.show', $item->id),
                            default => $archived || $association->is_archived
                                ? route('gis.officer.history', $item->id)
                                : route('gis.officer.index', ['location' => $item->id]),
                        };
                    @endphp

                    <h4 class="fo-record-title">
                        {{-- No drawer attributes: the user intentionally opens the full record. --}}
                        <a href="{{ $recordUrl }}"
                        class="fo-association-record-link">
                            {{ $title }}
                        </a>
                    </h4>

                    <span class="fo-pill {{ $archived ? 'fo-pill-amber' : 'fo-pill-slate' }}">
                        {{ $archived ? 'Archived · read-only' : 'Current record' }}
                    </span>

                    <dl class="fo-definition mt-4">
                        @foreach($fields as $label => $value)
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd>{{ filled($value) ? $value : 'Not recorded' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>
            @empty
                <p class="fo-warning">No records are available for this section.</p>
            @endforelse

            {{-- Pagination is handled inside the same drawer. --}}
            <div data-drawer-pagination>
                <x-management-pagination :records="$records"
                    :label="$titles[$section].' pagination'" />
            </div>
        @endif

        @if($section === 'address')
            <a href="{{ route('gis.officer.index') }}"
               class="fo-primary am-button-green">
                Open GIS Mapping
            </a>
        @endif
    </section>
</div>
</x-dashboard-layout>