@php
    $title = match ($type) {
        'production' => 'Production achievement',
        'income' => 'Income history',
        default => 'Inspection and material history',
    };
@endphp

<section data-monitoring-panel data-panel-title="{{ $title }}" class="am-monitoring-evidence">
    <header class="am-monitoring-context">
        <h3>{{ $record->project_title }}</h3>
        <p>{{ $record->association_name }}</p>
        <p class="am-monitoring-muted">Selected entry #{{ $record->id }}</p>
    </header>

    @if($type === 'production')
        @php
            $target = (float) $record->target_output;
            $actual = (float) $record->actual_output;
            $unit = \App\Services\MonitoringService::UNITS[$record->output_unit_code ?? ''] ?? 'Unit not recorded';
            $result = $target <= 0
                ? 'Not calculable'
                : ($actual >= $target ? 'Recorded target met' : 'Recorded target not yet met');
        @endphp

        <div class="am-monitoring-metrics">
            <div><span>Target Output</span><strong>{{ number_format($target, 2) }}</strong></div>
            <div><span>Actual Output</span><strong>{{ number_format($actual, 2) }}</strong></div>
            <div><span>Achievement</span><strong>{{ \App\Support\MonitoringProgress::label($target, $actual) }}</strong></div>
        </div>

        <dl class="am-monitoring-facts">
            <div><dt>Reporting period</dt><dd>{{ $record->quarter_name }} {{ $record->year }}</dd></div>
            <div><dt>Recorded unit</dt><dd>{{ $unit }}{{ $record->output_unit_spec ? ' / '.$record->output_unit_spec : '' }}</dd></div>
            <div><dt>Result</dt><dd>{{ $result }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ $record->updated_at ?? 'Not recorded' }}</dd></div>
        </dl>

        <section class="am-monitoring-note">
            <h3>What this measures</h3>
            <p>The percentage compares recorded actual output with the target for this project and quarter.</p>
            @if($target > 0)
                <p><strong>{{ number_format($actual, 2) }} &divide; {{ number_format($target, 2) }} &times; 100
                    = {{ \App\Support\MonitoringProgress::label($target, $actual) }}</strong></p>
                <p>{{ $actual >= $target
                    ? 'The recorded actual output equals or exceeds the target.'
                    : 'The recorded actual output is below the target.' }}</p>
            @else
                <p>A zero target cannot produce a meaningful achievement percentage.</p>
            @endif
            @if(!$record->output_unit_code)
                <p>The unit is unconfirmed. Verify the source record before interpreting these quantities.</p>
            @endif
        </section>

        <section class="am-monitoring-note">
            <h3>Recorded supporting remarks</h3>
            <p class="whitespace-pre-line">{{ $record->remarks ?: 'No supporting remarks recorded.' }}</p>
            <p class="am-monitoring-muted">
                This calculation does not establish the cause of the result or independently verify program success.
            </p>
        </section>

        <p class="am-monitoring-muted">
            {{ $record->year }} total:
            {{ $record->year_actual_total === null
                ? 'Unavailable because some units are unconfirmed or inconsistent.'
                : number_format($record->year_actual_total, 2).' '.$unit }}
            Missing quarters are not treated as zero output.
        </p>
    @elseif($type === 'income')
        @php
            $byMonth = $history->keyBy('month');
            $maximum = max(1, (float) $history->max('gross_income'));
            $total = $history->sum('gross_income');
        @endphp

        <nav class="am-monitoring-tabs" aria-label="Income history year">
            @foreach($years as $year)
                <a href="{{ route('officer.monitoring.details', [
                    'type' => 'income', 'record' => $record->id, 'history_year' => $year,
                ]) }}" data-monitoring-open="details"
                   class="{{ (int) $year === $historyYear ? 'is-active' : '' }}">
                    {{ $year }}
                </a>
            @endforeach
        </nav>

        <div class="am-monitoring-metrics">
            <div><span>History year</span><strong>{{ $historyYear }}</strong></div>
            <div><span>Recorded months</span><strong>{{ $history->count() }} / 12</strong></div>
            <div><span>Recorded gross income</span><strong>&#8369;{{ number_format($total, 2) }}</strong></div>
        </div>

        <figure>
            <figcaption class="am-monitoring-muted">
                Monthly gross income. Empty months are unrecorded, not zero.
            </figcaption>
            <div class="am-monitoring-chart" aria-hidden="true">
                @foreach(range(1, 12) as $month)
                    @php($entry = $byMonth->get($month))
                    <div class="am-monitoring-chart-column">
                        <div class="am-monitoring-chart-track">
                            @if($entry)
                                <span style="height: {{ round((float) $entry->gross_income / $maximum * 100, 2) }}%"></span>
                            @else
                                <em>&mdash;</em>
                            @endif
                        </div>
                        <small>{{ date('M', mktime(0, 0, 0, $month, 1)) }}</small>
                    </div>
                @endforeach
            </div>
        </figure>

        <div class="am-monitoring-table-wrap">
            <table class="am-monitoring-table">
                <caption class="sr-only">Recorded monthly income for {{ $historyYear }}</caption>
                <thead><tr><th>Month</th><th>Gross income</th><th>Change from previous recorded month</th><th>Supporting remarks</th></tr></thead>
                <tbody>
                @php($previous = null)
                @forelse($history as $entry)
                    @php($change = $previous ? (float) $entry->gross_income - (float) $previous->gross_income : null)
                    <tr>
                        <td>{{ date('F', mktime(0, 0, 0, $entry->month, 1)) }} {{ $entry->year }}</td>
                        <td>&#8369;{{ number_format($entry->gross_income, 2) }}</td>
                        <td>
                            @if($previous)
                                {{ $change > 0 ? '+' : '' }}{{ number_format($change, 2) }} PHP
                                <p class="am-monitoring-muted">Compared with {{ date('F', mktime(0, 0, 0, $previous->month, 1)) }}</p>
                            @else
                                No earlier recorded month in this year
                            @endif
                        </td>
                        <td>
                            <p class="whitespace-pre-line">{{ $entry->remarks ?: 'No remarks recorded.' }}</p>
                            <p class="am-monitoring-muted">Entry #{{ $entry->id }} · Updated {{ $entry->updated_at ?? 'Not recorded' }}</p>
                        </td>
                    </tr>
                    @php($previous = $entry)
                @empty
                    <tr><td colspan="4">No income records for this year.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <p class="am-monitoring-muted">
            Gross income is income before expenses, not profit. The total includes recorded months only.
            Changes compare recorded amounts; the system does not infer their cause.
        </p>
    @else
        <dl class="am-monitoring-facts">
            <div><dt>Material</dt><dd>{{ $material->item_name }}</dd></div>
            <div><dt>Quantity / unit</dt><dd>{{ $material->quantity }} {{ $material->unit }}</dd></div>
            <div><dt>Delivery date</dt><dd>{{ $material->delivery_date ?? 'Not recorded' }}</dd></div>
            <div><dt>Material register status</dt><dd>{{ $material->register_status ?? 'Not recorded' }}</dd></div>
            <div><dt>Archive state</dt><dd>{{ $material->archived_at ? 'Archived' : 'Current' }}</dd></div>
        </dl>

        <p class="am-monitoring-muted">
            Observations appear newest first. Undated entries appear last; their inspection dates are unknown.
            Delivery information comes from the material register.
        </p>

        <ol class="am-monitoring-timeline">
            @foreach($history as $entry)
                <li>
                    <div class="am-monitoring-timeline-heading">
                        <strong>{{ $entry->observed_on ?? 'Inspection date not recorded' }}</strong>
                        @include('shared.partials.badge', ['label' => $entry->status_name ?? 'Not recorded'])
                    </div>
                    <p class="am-monitoring-muted">
                        Entry #{{ $entry->id }}{{ $entry->id === $record->id ? ' / Selected entry' : '' }}
                    </p>
                    <p>{{ $entry->material_description ?: 'No description recorded.' }}</p>
                    <dl class="am-monitoring-facts">
                        <div><dt>Scheduled maintenance</dt><dd>{{ $entry->scheduled_maintenance ?? 'Not recorded' }}</dd></div>
                        <div><dt>Actual maintenance</dt><dd>{{ $entry->actual_maintenance ?? 'Not recorded' }}</dd></div>
                    </dl>
                    <p class="whitespace-pre-line">{{ $entry->remarks ?: 'No remarks recorded.' }}</p>
                    <p class="am-monitoring-muted">
                        Created {{ $entry->created_at ?? 'Not recorded' }} ·
                        Updated {{ $entry->updated_at ?? 'Not recorded' }}
                    </p>
                </li>
            @endforeach
        </ol>

        <nav class="am-monitoring-actions" aria-label="Inspection history pagination">
            @if($history->previousPageUrl())
                <a class="fo-action" href="{{ $history->previousPageUrl() }}" data-monitoring-open="details">Previous</a>
            @endif
            <span>Page {{ $history->currentPage() }} of {{ $history->lastPage() }}</span>
            @if($history->nextPageUrl())
                <a class="fo-action" href="{{ $history->nextPageUrl() }}" data-monitoring-open="details">Next</a>
            @endif
        </nav>

        <p class="am-monitoring-muted">
            These are the latest saved values of each observation. Corrections remain recorded in the audit log;
            they are not presented as additional inspections.
        </p>
    @endif
</section>