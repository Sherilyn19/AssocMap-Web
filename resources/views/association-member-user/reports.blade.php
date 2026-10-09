<x-dashboard-layout title="Reports & Analytics" topbar-title="Reports & Analytics">
    <div data-member-workspace class="mx-auto max-w-[1600px] space-y-6 p-4 sm:p-6 lg:p-8">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-blue-800">My association</p>
            <h1 class="mt-2 text-2xl font-bold">Reports & Analytics</h1>
            <p class="mt-2 font-medium">
                {{ $rows->first()?->name ?? 'No current association report available' }}
            </p>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Summaries and downloads contain only your association’s records.
            </p>
        </header>

        @include('shared.partials.feedback')

        <form method="GET"
              action="{{ route('member.reports.index') }}"
              class="flex flex-wrap items-end gap-4 rounded-xl border bg-white p-5">
            <label class="block text-sm font-semibold">
                Reporting year
                <input class="am-user-control mt-2"
                       type="number"
                       name="year"
                       min="1900"
                       max="2100"
                       required
                       value="{{ $year }}">
            </label>

            <button class="am-user-button am-user-button-primary">
                Apply filter
            </button>

            <a href="{{ route('member.reports.export', ['year' => $year]) }}"
               class="am-user-button am-user-button-secondary">
                Download CSV
            </a>
        </form>

        <p class="text-sm leading-6 text-slate-600">
            Member and project counts show current records. Training dates and
            monitoring periods use {{ $year }}. Archived associations, projects,
            members, and trainings are excluded from this report.
        </p>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                 aria-label="Association report summary">
            @foreach ([
                'Current members' => number_format($counts['members']),
                'Current projects' => number_format($counts['projects']),
                'Trainings in selected year' => number_format($counts['trainings']),
                'Recorded gross income' => 'PHP '.number_format((float) $incomeTotal, 2),
            ] as $label => $value)
                <article class="am-user-summary">
                    <h2 class="text-sm font-semibold text-slate-600">{{ $label }}</h2>
                    <p class="mt-3 break-words text-2xl font-bold tabular-nums">
                        {{ $value }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Monthly income</h2>
            <p class="mt-2 text-sm text-slate-600">
                Gross income in Philippine pesos. A missing record is not a recorded zero.
            </p>

            <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($months as $month)
                    <div class="rounded-lg bg-slate-50 p-4">
                        <dt class="text-sm text-slate-600">{{ $month['label'] }}</dt>
                        <dd class="mt-1 font-semibold tabular-nums">
                            {{ $month['records']
                                ? 'PHP '.number_format((float) $month['total'], 2)
                                : 'No records' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Project status summary</h2>
            <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                @forelse ($projectStatuses as $status)
                    <div class="rounded-lg bg-slate-50 p-4">
                        <dt>{{ $status->status_name ?? 'Not recorded' }}</dt>
                        <dd class="mt-1 font-semibold">{{ number_format($status->total) }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No current projects recorded.</p>
                @endforelse
            </dl>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Quarterly production</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Production quantities remain separate because project units can differ.
            </p>

            <div class="mt-4 overflow-x-auto"
                 tabindex="0"
                 role="region"
                 aria-label="Quarterly production records">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">
                        Production targets and actual output for {{ $year }}
                    </caption>
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach (['Project', 'Quarter', 'Target', 'Actual', 'Achievement'] as $heading)
                                <th scope="col" class="p-3">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($production as $record)
                            <tr class="border-t border-slate-200">
                                <th scope="row" class="p-3 font-medium">{{ $record->title }}</th>
                                <td class="p-3">{{ $record->quarter_name }}</td>
                                <td class="p-3 tabular-nums">{{ number_format((float) $record->target_output, 2) }}</td>
                                <td class="p-3 tabular-nums">{{ number_format((float) $record->actual_output, 2) }}</td>
                                <td class="p-3 tabular-nums">
                                    {{ (float) $record->target_output > 0
                                        ? number_format((float) $record->actual_output / (float) $record->target_output * 100, 1).'%'
                                        : 'N/A (zero target)' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-6 text-center text-slate-600">
                                    No production records for this reporting year.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs text-slate-500">
            Generated {{ $generatedAt->format('M j, Y, g:i a') }} (Asia/Manila).
        </p>
    </div>
</x-dashboard-layout>