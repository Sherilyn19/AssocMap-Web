<x-dashboard-layout title="Reports & Analytics" topbar-title="Reports & Analytics">
    <div class="space-y-6" data-reports>
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">BFAR SAAD Phase II</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Reports & Analytics</h1>
                <p class="mt-2 text-sm text-slate-600">Review association coverage, project progress, and recorded performance.</p>
            </div>
            @unless ($unavailable ?? false)
                <a href="{{ route('reports.export', $filters) }}" class="inline-flex min-h-11 items-center rounded-lg bg-assocmap-primary px-5 py-2 text-sm font-semibold text-white hover:opacity-90">Download CSV</a>
            @endunless
        </header>

        @if ($unavailable ?? false)
            <section role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="font-semibold text-amber-950">Reports are temporarily unavailable</h2>
                <p class="mt-2 text-sm text-amber-900">We could not load the records. Please try again shortly.</p>
                <a href="{{ route('reports.index', request()->only(['year', 'area_unit_id', 'association_id'])) }}" class="mt-4 inline-block font-semibold underline">Try again</a>
            </section>
        @else
            {{-- Keep the selected filters in the download link so the file matches the report. --}}
            <form action="{{ route('reports.index') }}" method="GET" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @if ($errors->any())
                    <div role="alert" class="mb-4 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif
                <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="block text-sm font-medium text-slate-700">Reporting year
                        <input type="number" name="year" min="1900" max="2100" required value="{{ $filters['year'] }}" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Municipality
                        <select name="area_unit_id" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">All municipalities</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}" @selected(($filters['area_unit_id'] ?? '') == $area->id)>{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Association
                        <select name="association_id" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">All current associations</option>
                            @foreach ($associations as $association)
                                <option value="{{ $association->id }}" @selected(($filters['association_id'] ?? '') == $association->id)>{{ $association->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-center gap-4">
                        <button type="submit" class="min-h-11 rounded-lg bg-assocmap-primary px-5 py-2 text-sm font-semibold text-white">Apply filters</button>
                        <a href="{{ route('reports.index') }}" class="text-sm font-semibold text-slate-600 underline">Reset</a>
                    </div>
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">Association, member, and project counts show current records. Trainings use their scheduled or conducted date in {{ $filters['year'] }}; income and production use their recorded period in {{ $filters['year'] }}. Archived associations and archived records are excluded. Both location filters apply together.</p>
            </form>

            <section aria-label="Report summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (['associations' => 'Current associations', 'members' => 'Current members', 'projects' => 'Current projects', 'trainings' => 'Trainings in selected year'] as $key => $label)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-600">{{ $label }}</h2>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($counts[$key]) }}</p>
                    </div>
                @endforeach
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-3">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2" aria-labelledby="income-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h2 id="income-heading" class="font-semibold text-slate-900">Monthly recorded income</h2><p class="mt-1 text-xs text-slate-500">Gross income in Philippine pesos · {{ $filters['year'] }}</p></div>
                        <p class="text-xl font-bold text-assocmap-primary">PHP {{ number_format((float) $incomeTotal, 2) }}</p>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">{{ number_format($incomeRecords) }} submitted records. A month without records has no reported income data.</p>
                    @php($maximumIncome = max(1, (float) $months->max('total')))
                    <div class="mt-5 space-y-3">
                        @foreach ($months as $month)
                            <div class="grid grid-cols-3 items-center gap-3 text-xs sm:text-sm">
                                <span class="text-slate-600">{{ $month['label'] }}</span>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-assocmap-primary" style="width: {{ max(0, min(100, (float) $month['total'] / $maximumIncome * 100)) }}%"></div></div>
                                <span class="text-right tabular-nums text-slate-700">{{ $month['records'] ? number_format((float) $month['total'], 2) : 'No records' }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="status-heading">
                    <h2 id="status-heading" class="font-semibold text-slate-900">Project implementation</h2>
                    <p class="mt-1 text-xs text-slate-500">Current status across the selected associations.</p>
                    <div class="mt-5 space-y-5">
                        @forelse ($projectStatuses as $status)
                            <div>
                                <div class="mb-2 flex justify-between gap-3 text-sm"><span>{{ $status->status_name ?? 'Unspecified' }}</span><span class="font-semibold">{{ number_format($status->total) }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-blue-600" style="width: {{ $counts['projects'] ? $status->total / $counts['projects'] * 100 : 0 }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No current projects match these filters.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="associations-heading">
                <div class="border-b border-slate-100 p-5"><h2 id="associations-heading" class="font-semibold text-slate-900">Association summary</h2><p class="mt-1 text-xs text-slate-500">Compare current membership and projects with activity in the selected year.</p></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-600"><tr>
                            @foreach (['Association', 'Municipality', 'Members', 'Projects', 'Trainings', 'Gross income (PHP)'] as $heading)<th scope="col" class="px-5 py-3">{{ $heading }}</th>@endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($rows as $row)
                                <tr><th scope="row" class="px-5 py-4 font-medium"><a href="{{ route('admin.associations.show', $row->id) }}" class="text-assocmap-primary hover:underline">{{ $row->name }}</a></th><td class="px-5 py-4">{{ $row->municipality ?? 'Unassigned' }}</td><td class="px-5 py-4">{{ number_format($row->members) }}</td><td class="px-5 py-4">{{ number_format($row->projects) }}</td><td class="px-5 py-4">{{ number_format($row->trainings) }}</td><td class="px-5 py-4 tabular-nums">{{ number_format((float) $row->income, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No current associations match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="production-heading">
                <div class="border-b border-slate-100 p-5"><h2 id="production-heading" class="font-semibold text-slate-900">Production performance · {{ $filters['year'] }}</h2><p class="mt-1 text-xs leading-5 text-slate-500">Actual output divided by target output. Quantities are kept separate because project units can differ. Check remarks for unit details.</p></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-600"><tr>
                            @foreach (['Association / project', 'Quarter', 'Target', 'Actual', 'Achievement', 'Remarks'] as $heading)<th scope="col" class="px-5 py-3">{{ $heading }}</th>@endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($production as $row)
                                <tr><th scope="row" class="px-5 py-4 font-medium">{{ $row->title }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ $row->association }}</span></th><td class="px-5 py-4">{{ $row->quarter_name }}</td><td class="px-5 py-4">{{ number_format((float) $row->target_output, 2) }}</td><td class="px-5 py-4">{{ number_format((float) $row->actual_output, 2) }}</td><td class="px-5 py-4">{{ (float) $row->target_output > 0 ? number_format((float) $row->actual_output / (float) $row->target_output * 100, 1).'%' : 'N/A (zero target)' }}</td><td class="max-w-xs break-words px-5 py-4 text-slate-600">{{ $row->remarks ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No production records match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="text-xs text-slate-500">Generated {{ $generatedAt->format('M j, Y, g:i a') }} (Asia/Manila). Reports reflect saved records at the time of loading.</p>
        @endif
    </div>
</x-dashboard-layout>
