@php
    $periodLabel = $overview['year'] ?? 'All years';

    $chartTitles = [
        'projects' => 'Current project status',
        'attendance' => 'Training attendance',
        'archived_projects' => 'Archived project status',
        'archived_attendance' => 'Archived training attendance',
    ];

    $representative = $association->representative;
    $representativeName = $representative
        && !$representative->is_archived
        && (int) $representative->association_id === (int) $association->id
        ? trim(implode(' ', array_filter([
            $representative->first_name,
            $representative->middle_name,
            $representative->last_name,
        ])))
        : null;
@endphp

<section class="fo-association-overview" aria-label="Association overview">
    <header class="fo-overview-heading">
        <div>
            <p class="fo-eyebrow">BFAR SAAD Phase II</p>
            <h3>Field visit overview</h3>
        </div>
        <span class="fo-pill fo-pill-teal">Current records</span>
    </header>

    @php
        // Reuse the existing authorized drawer routes.
        // Card totals represent current records, independent of the year filter.
        $cardSections = [
            'Official members' => 'members',
            'Current projects' => 'projects',
            'Current training records' => 'trainings',
            'Public GIS locations' => 'address',
        ];
    @endphp

    <dl class="fo-overview-totals">
        @foreach($overview['totals'] as $label => $count)
            @php
                $parameters = [
                    'association' => $association->id,
                    'section' => $cardSections[$label],
                ];

                // Match the public GIS card count using the same server-side scope.
                if ($label === 'Public GIS locations') {
                    $parameters['public_only'] = 1;
                }
            @endphp

            <div class="fo-overview-card">
                <dt>{{ $label }}</dt>

                <dd>
                    {{ number_format($count) }}

                    {{-- The stretched link makes the whole card clickable.
                        Existing drawer JavaScript handles this attribute. --}}
                    <a href="{{ route('officer.associations.details', $parameters) }}"
                    data-record-association
                    class="fo-overview-card-link"
                    aria-label="View {{ strtolower($label) }}: {{ number_format($count) }}">
                        <span class="sr-only">View {{ strtolower($label) }}</span>
                    </a>
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="fo-overview-reference">
        <div>
            <span>Designated representative</span>

            @if($representativeName)
                <a href="{{ route('membership.members.show', $representative->id) }}">
                    {{ $representativeName }}
                </a>
            @else
                <strong>Not recorded</strong>
            @endif
        </div>

        <div>
            <div class="fo-overview-help">
                <span>Association record updated</span>

                {{-- Reuse the existing Heroicon information component. --}}
                <x-info label="About the record update date">
                    When this association record was last updated in AssocMap.
                    This is not a field visit or verification date.
                </x-info>
            </div>

            <strong>
                {{ $association->updated_at?->timezone('Asia/Manila')->format('M d, Y · g:i A')
                    ?? 'Not recorded' }}
            </strong>
        </div>
    </div>

    {{-- A normal GET also works when the profile is opened as a full page. --}}
    {{-- Identify this form for filtering inside the open modal. --}}
    <form method="GET"
        action="{{ route('officer.associations.show', $association->id) }}"
        class="fo-overview-filters"
        data-record-filter>
        <label>
            Reporting year
            <select name="year">
                @php
                    $years = collect($overview['years'])
                        ->when($overview['year'], fn ($years) => $years
                            ->push($overview['year']))
                        ->unique()->sortDesc();
                @endphp

                @foreach($years as $year)
                    <option value="{{ $year }}"
                        @selected($overview['year'] === (int) $year)>
                        {{ $year }}
                    </option>
                @endforeach

                <option value="all" @selected($overview['year'] === null)>
                    All years
                </option>
            </select>
        </label>

        <label class="fo-overview-check">
            <input type="checkbox" name="include_archived" value="1"
                   @checked($overview['includeArchived'])>
            Include archived records
        </label>

        <button type="submit" class="fo-primary am-button-green">
            Apply
        </button>
    </form>

    <p class="fo-overview-caption">
        {{ $periodLabel }} · Current totals above are not filtered by year.
    </p>

    <div class="fo-overview-charts">
        @foreach($overview['series'] as $key => $rows)
            <section class="fo-overview-chart">
                <div class="fo-overview-help">
                    <h4>{{ $chartTitles[$key] }}</h4>

                    <x-info :label="'About '.strtolower($chartTitles[$key])">
                        @if(str_contains($key, 'attendance'))
                            Participant entries for trainings dated on or before today.
                            Attendance across different trainings is counted separately.
                            Missing attendance decisions remain Not recorded.
                        @else
                            Current recorded status of projects with implementation dates
                            in the selected period. This does not represent their
                            historical status at year-end.
                        @endif
                    </x-info>
                </div>

                @if(array_sum(array_column($rows, 'count')) === 0)
                    <p class="fo-overview-empty">No matching records.</p>
                @else
                    @foreach($rows as $index => $row)
                        @php
                            $percent = $overview['charts'][$key][$index]['percent']
                                ?? null;
                        @endphp

                        <div class="fo-overview-bar-row">
                            <div class="fo-overview-bar-label">
                                <span>{{ $row['label'] }}</span>
                                <strong>{{ number_format($row['count']) }}</strong>
                            </div>

                            @if($percent !== null)
                                <div class="fo-overview-track" aria-hidden="true">
                                    <span style="--bar-width: {{ (float) $percent }}%"></span>
                                </div>
                                <small>{{ number_format($percent, 1) }}%</small>
                            @endif
                        </div>
                    @endforeach
                @endif
            </section>
        @endforeach
    </div>

    @if($overview['charts'] === null)
        <p class="fo-overview-caption" role="status">
            Chart rendering is unavailable. Recorded counts are shown above.
        </p>
    @endif

    <section class="fo-overview-timeline">
        <header class="fo-overview-heading">
            <h4>Dated activity</h4>
            <span>Latest 12 dated records · {{ $periodLabel }}</span>
        </header>

        <p class="fo-overview-caption">
            Recorded dates, not audit history or proof of completion.
        </p>

        <ol>
            @forelse($overview['timeline'] as $event)
                <li>
                    <time datetime="{{ $event['date'] }}">
                        {{ \Carbon\Carbon::parse($event['date'])->format('M d, Y') }}
                    </time>

                    <div>
                        <small>{{ $event['kind'] }}</small>
                        <a href="{{ $event['url'] }}">{{ $event['name'] }}</a>

                        @if($event['archived'])
                            <span class="fo-pill fo-pill-amber">Archived</span>
                        @endif

                        @if($event['future'])
                            <span class="fo-pill fo-pill-slate">Future date</span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="fo-overview-empty">
                    No dated records for this selection.
                </li>
            @endforelse
        </ol>
    </section>

    @if(array_sum($overview['undated']) > 0)
        <details class="fo-overview-missing">
            <summary>
                Missing dates in current records
                ({{ number_format(array_sum($overview['undated'])) }})
            </summary>

            <p>These records cannot be assigned to a reporting year.</p>

            <ul>
                @foreach($overview['undated'] as $label => $count)
                    @if($count > 0)
                        <li>{{ $label }}: {{ number_format($count) }}</li>
                    @endif
                @endforeach
            </ul>
        </details>
    @endif
</section>