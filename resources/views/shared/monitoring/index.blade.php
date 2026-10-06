<x-dashboard-layout title="Monitoring Module">
<div class="pm-page mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-slate-900">Monitoring Module</h1><p class="mt-1 text-sm text-slate-600">Track project production, monthly income, and material maintenance.</p></div>
        <a class="pm-primary" href="{{ route('monitoring.create', $type) }}">Add {{ $types[$type] }} Record</a>
    </header>
    @include('shared.partials.feedback')
    <nav aria-label="Monitoring categories" class="flex flex-wrap gap-2">
        @foreach($types as $key => $label)
            <a class="{{ $type === $key ? 'pm-primary' : 'pm-action border border-slate-300 bg-white' }}" href="{{ route('monitoring.index', ['type' => $key]) }}" @if($type === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
        <input type="hidden" name="type" value="{{ $type }}">
        <div><label for="search" class="pm-label">Search</label><input id="search" name="search" class="pm-input" value="{{ $filters['search'] ?? '' }}" placeholder="Project or association" maxlength="255"></div>
        <div><label for="project_id" class="pm-label">Project</label><select id="project_id" name="project_id" class="pm-input"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(($filters['project_id'] ?? '') == $project->id)>{{ $project->title }} — {{ $project->association_name }}</option>@endforeach</select></div>
        @if($type !== 'materials')<div><label for="year" class="pm-label">Year</label><input id="year" name="year" type="number" min="1900" max="2100" class="pm-input" value="{{ $filters['year'] ?? '' }}" placeholder="All years"></div>@endif
        <div class="flex items-end gap-2"><button class="pm-primary" type="submit">Apply Filters</button><a class="pm-action border border-slate-300" href="{{ route('monitoring.index', ['type' => $type]) }}">Reset</a></div>
    </form>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-4"><h2 class="font-semibold">{{ $types[$type] }} Records <span class="text-sm font-normal text-slate-600">({{ $records->total() }})</span></h2></div>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable records"><table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="p-4">Project / Association</th>
                @if($type === 'production')<th class="p-4">Period</th><th class="p-4">Target / Actual</th><th class="p-4">Achievement</th>
                @elseif($type === 'income')<th class="p-4">Period</th><th class="p-4">Gross Income</th>
                @else<th class="p-4">Material / Condition</th><th class="p-4">Maintenance</th>@endif
                <th class="p-4">Remarks</th><th class="p-4">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-200">
            @forelse($records as $record)
                @php
                // An archived material has the same read-only protection as its archived parent.
                $recordReadOnly = $record->project_archived
                    || $record->association_archived
                    || ($type === 'materials' && $record->material_archived_at !== null);
            @endphp
                <tr class="align-top {{ !$record->terminated_on && $record->project_status_name === 'Ongoing' ? 'bg-blue-50/50' : '' }}">
                    <td class="p-4">
                        <p class="font-semibold">{{ $record->project_title }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ $record->association_name }}</p>
                        <div class="mt-2">
                            @include('shared.partials.badge', ['label' => $record->terminated_on ? 'Terminated' : ($record->project_status_name ?? 'Not recorded')])
                        </div>
                        @if($record->terminated_on)
                            <p class="mt-1 text-xs">Terminated: {{ $record->terminated_on }}</p>
                        @endif
                        @if($recordReadOnly)
                            <span class="text-xs text-amber-800">Archived — read only</span>
                        @endif
                    </td>
                    @if($type === 'production')
                        <td class="whitespace-nowrap p-4">{{ $record->quarter_name }} {{ $record->year }}</td>
                        <td class="p-4">
                        <p>Target: {{ number_format($record->target_output, 2) }}</p>
                        <p>Actual: {{ number_format($record->actual_output, 2) }}</p>

                        <p class="mt-1 text-xs text-slate-600">
                            {{ \App\Services\MonitoringService::UNITS[$record->output_unit_code] ?? 'Unit not recorded' }}
                            @if($record->output_unit_spec)
                                — {{ $record->output_unit_spec }}
                            @endif
                        </p>

                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm underline">Yearly production</summary>
                            <div class="mt-2 rounded-lg border bg-slate-50 p-3 text-sm">
                                @if($record->year_actual_total === null)
                                    Annual total unavailable: some entries have unconfirmed units.
                                @else
                                    <p>
                                        {{ $record->year }} actual output:
                                        <strong>{{ number_format($record->year_actual_total, 2) }}</strong>
                                    </p>
                                    <p class="mt-1">
                                        Sum of recorded quarters for this project and year,
                                        using the same confirmed unit.
                                    </p>
                                    <p class="mt-1 text-xs">
                                        Missing quarters are not assumed to have zero production.
                                    </p>
                                @endif
                            </div>
                        </details>
                    </td>
                        <td class="p-4">{{ \App\Support\MonitoringProgress::label($record->target_output, $record->actual_output) }}<p class="mt-1 text-xs text-slate-600">Actual ÷ Target × 100</p></td>
                    @elseif($type === 'income')
                        <td class="whitespace-nowrap p-4">{{ date('F', mktime(0, 0, 0, $record->month, 1)) }} {{ $record->year }}</td><td class="p-4">@include('shared.monitoring.partials.income-details')</td>
                    @else
                        <td class="p-4"><p class="font-medium">{{ $record->item_name }}</p>
                        {{-- Preserve undated legacy entries without inventing inspection dates. --}}
                        <p class="mt-1 text-xs text-slate-600">
                            Observed:
                            {{ $record->observed_on ?? 'Date not recorded' }}
                            · Entry #{{ $record->id }}
                        </p>
                        <p>@include('shared.partials.badge', ['label' => $record->status_name ?? 'Not recorded'])</p><p class="mt-1 text-xs text-slate-600">{{ $record->material_description }}</p></td>
                        <td class="whitespace-nowrap p-4"><p>Scheduled: {{ $record->scheduled_maintenance ?? 'Not set' }}</p><p>Actual: {{ $record->actual_maintenance ?? 'Not recorded' }}</p>@if($record->scheduled_maintenance && !$record->actual_maintenance && $record->scheduled_maintenance < now('Asia/Manila')->toDateString())<p class="mt-1 font-semibold text-red-700">Maintenance overdue</p>@endif</td>
                    @endif
                    <td class="max-w-xs whitespace-pre-line break-words p-4">{{ $record->remarks ?: '—' }}</td>
                    <td class="p-4">@if(!$recordReadOnly)<a class="pm-action border border-slate-300" href="{{ route('monitoring.edit', [$type, $record->id]) }}">Edit<span class="sr-only"> {{ $record->project_title }} record {{ $record->id }}</span></a>@else<span class="text-slate-500">Read only</span>@endif</td>
                </tr>
            @empty<tr><td colspan="6" class="p-10 text-center text-slate-600">No monitoring records found. Add a record or adjust the filters.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-200 p-4">{{ $records->links() }}</div>
    </section>
</div>
</x-dashboard-layout>
