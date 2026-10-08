<x-dashboard-layout title="Monitoring Module">
<div class="fo-coverage am-monitoring-page space-y-5" data-management-register>
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
            <h1 class="mt-3 text-3xl font-bold">Monitoring Module</h1>
            <p class="mt-2 text-sm text-slate-600">
                Review recorded output, income, and material observations.
            </p>
        </div>

        {{-- Production opens a quarter; dated entries are added inside Achievement. --}}
        <a href="{{ $type === 'production'
                ? route('officer.production-progress.create')
                : route('monitoring.create', $type) }}"
        data-monitoring-open="create"
        class="fo-action am-button-green">
            <span aria-hidden="true">+</span>
            {{ $type === 'production' ? 'Open production quarter' : 'Add '.$types[$type].' Record' }}
        </a>
    </header>

    @include('shared.partials.feedback')

    <nav class="am-monitoring-tabs" aria-label="Monitoring categories">
        @foreach($types as $key => $label)
            <a href="{{ route('monitoring.index', ['type' => $key]) }}"
               class="{{ $key === $type ? 'is-active' : '' }}"
               @if($key === $type) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('monitoring.index') }}" class="fo-filter-card">
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="fo-section-heading">
            <h2 class="font-semibold">Filter monitoring records</h2>
            <span class="fo-pill fo-pill-teal">Your assignments only</span>
        </div>

        <div class="am-monitoring-filters">
            <label>
                <span>Search</span>
                <input name="search" type="search" maxlength="255"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Project or association">
            </label>

            <label>
                <span>Association</span>
                <select name="association_id">
                    <option value="">All assigned associations</option>
                    @foreach($associations as $association)
                        <option value="{{ $association->id }}"
                            @selected(($filters['association_id'] ?? '') == $association->id)>
                            {{ $association->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Project</span>
                <select name="project_id">
                    <option value="">All assigned projects</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}"
                            @selected(($filters['project_id'] ?? '') == $project->id)>
                            {{ $project->title }} &mdash; {{ $project->association_name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Record access</span>
                <select name="archive">
                    @foreach([
                        'all' => 'All records',
                        'current' => 'Current records',
                        'archived' => 'Archived / read-only history',
                    ] as $value => $label)
                        <option value="{{ $value }}"
                            @selected(($filters['archive'] ?? 'all') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            @if($type !== 'materials')
                <label>
                    <span>Year</span>
                    <input name="year" type="number" min="1900" max="2100"
                           value="{{ $filters['year'] ?? '' }}" placeholder="All years">
                </label>

                <label>
                    <span>{{ $type === 'production' ? 'Quarter' : 'Month' }}</span>
                    <select name="{{ $type === 'production' ? 'quarter_id' : 'month' }}">
                        <option value="">All periods</option>
                        @if($type === 'production')
                            @foreach($quarters as $id => $name)
                                <option value="{{ $id }}"
                                    @selected(($filters['quarter_id'] ?? '') == $id)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        @else
                            @foreach(range(1, 12) as $month)
                                <option value="{{ $month }}"
                                    @selected(($filters['month'] ?? '') == $month)>
                                    {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </label>
            @else
                <label>
                    <span>Condition</span>
                    <select name="condition">
                        <option value="">All conditions</option>
                        @foreach(\App\Services\MonitoringService::CONDITIONS as $condition)
                            <option @selected(($filters['condition'] ?? '') === $condition)>
                                {{ $condition }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Observation date</span>
                    <select name="observation">
                        @foreach(['all' => 'All entries', 'dated' => 'Dated entries', 'undated' => 'Date not recorded'] as $value => $label)
                            <option value="{{ $value }}"
                                @selected(($filters['observation'] ?? 'all') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Observed from</span>
                    <input name="observed_from" type="date"
                           value="{{ $filters['observed_from'] ?? '' }}">
                </label>

                <label>
                    <span>Observed through</span>
                    <input name="observed_to" type="date"
                           value="{{ $filters['observed_to'] ?? '' }}">
                </label>
            @endif
        </div>

        <div class="am-monitoring-filter-footer">
            <span>{{ $records->total() }} matching records</span>
            <div class="am-monitoring-actions">
                <a class="fo-action" href="{{ route('monitoring.index', ['type' => $type]) }}">
                    Reset
                </a>
                <button class="fo-action am-button-green" type="submit">Apply filters</button>
            </div>
        </div>
    </form>

    <section class="fo-card overflow-hidden">
        <div class="fo-section-heading">
            <h2 class="font-semibold">{{ $types[$type] }} records</h2>
            <span class="fo-pill">{{ $records->total() }} records</span>
        </div>

        <div class="am-monitoring-table-wrap" tabindex="0" role="region"
             aria-label="{{ $types[$type] }} monitoring records">
            <table class="am-monitoring-table">
                <thead>
                    <tr>
                        <th>Project / Association</th>
                        <th>{{ $type === 'materials' ? 'Material / Observation' : 'Period' }}</th>
                        <th>{{ $type === 'production' ? 'Target / Actual' : ($type === 'income' ? 'Gross income' : 'Condition / Maintenance') }}</th>
                        @if($type === 'production')<th>Achievement</th>@endif
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($records as $record)
                    @php
                        $readOnly = $record->project_archived || $record->association_archived
                            || ($type === 'materials' && $record->material_archived_at !== null);
                        $detailUrl = route('officer.monitoring.details', [$type, $record->id]);
                        $unit = \App\Services\MonitoringService::UNITS[$record->output_unit_code ?? ''] ?? 'Unit not recorded';
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $record->project_title }}</strong>
                            <p class="am-monitoring-muted">{{ $record->association_name }}</p>
                            @if($readOnly)<span class="am-monitoring-muted">Archived &mdash; read only</span>@endif
                        </td>
                        <td>
                            @if($type === 'materials')
                                <strong>{{ $record->item_name }}</strong>
                                <a href="{{ $detailUrl }}" data-monitoring-open="details"
                                   class="am-monitoring-link">
                                    {{ $record->observed_on ?? 'Date not recorded' }}
                                    <span class="am-monitoring-muted">View inspection history</span>
                                </a>
                            @else
                                {{ $type === 'production'
                                    ? $record->quarter_name
                                    : date('F', mktime(0, 0, 0, $record->month, 1)) }}
                                {{ $record->year }}
                            @endif
                            <p class="am-monitoring-muted">Entry #{{ $record->id }}</p>
                        </td>
                        <td>
                            @if($type === 'production')
                                <p>Target: {{ number_format($record->target_output, 2) }}</p>
                                <p>Actual: {{ number_format($record->actual_output, 2) }}</p>
                                <p class="am-monitoring-muted">
                                    {{ $unit }}{{ $record->output_unit_spec ? ' / '.$record->output_unit_spec : '' }}
                                </p>
                            @elseif($type === 'income')
                                <a href="{{ $detailUrl }}" data-monitoring-open="details"
                                   class="am-monitoring-link">
                                    &#8369;{{ number_format($record->gross_income, 2) }}
                                    <span class="am-monitoring-muted">View monthly history</span>
                                </a>
                            @else
                                @include('shared.partials.badge', ['label' => $record->status_name ?? 'Not recorded'])
                                <p class="am-monitoring-muted">Scheduled: {{ $record->scheduled_maintenance ?? 'Not recorded' }}</p>
                                <p class="am-monitoring-muted">Completed: {{ $record->actual_maintenance ?? 'Not recorded' }}</p>
                            @endif
                        </td>
                        @if($type === 'production')
                            <td>
                                <a href="{{ $detailUrl }}" data-monitoring-open="achievement"
                                   class="am-monitoring-link">
                                    {{ \App\Support\MonitoringProgress::label($record->target_output, $record->actual_output) }}
                                    <span class="am-monitoring-muted">View calculation</span>
                                </a>
                            </td>
                        @endif
                        <td><p class="am-monitoring-remarks">{{ $record->remarks ?: 'No remarks recorded.' }}</p></td>
                        <td>
                            <div class="am-monitoring-actions">
                                @if($type !== 'production')
                                    <a href="{{ $detailUrl }}" data-monitoring-open="details"
                                       class="fo-action">History</a>
                                @endif
                                @if(!$readOnly)
                                    <a href="{{ route('monitoring.edit', [$type, $record->id]) }}"
                                       data-monitoring-open="edit"
                                       class="fo-action am-button-warning">Edit</a>
                                @else
                                    <span class="am-monitoring-muted">Read only</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $type === 'production' ? 6 : 5 }}">
                        No monitoring records found. Adjust the filters or add a record.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <x-management-pagination :records="$records" :numbered="true"
                                 label="Monitoring records pagination" />
    </section>
</div>
</x-dashboard-layout>