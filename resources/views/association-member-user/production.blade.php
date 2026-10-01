<x-dashboard-layout title="Production Records" topbar-title="Production Records">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => 'Production Records', 'description' => 'Compare quarterly target and actual output for your association’s projects. Records are read-only; quantities are not combined across projects.'])
    <form method="GET" class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end">
        <label class="min-w-0 flex-1 text-sm font-semibold">Search project<input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="255" class="am-user-control mt-2" placeholder="Enter a project title"></label>
        <label class="text-sm font-semibold">Year<select name="year" class="am-user-control mt-2"><option value="">All recorded years</option>@foreach ($years as $year)<option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>@endforeach</select></label>
        <button class="am-user-button am-user-button-primary">Apply filters</button><a class="am-user-button am-user-button-secondary" href="{{ route('member.production') }}">Reset</a>
    </form>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">Quarterly output <span class="text-slate-500">({{ $records->total() }})</span></h2></div>
        <div class="am-member-records"><table class="w-full text-left text-sm"><caption class="sr-only">Quarterly production: target versus actual output</caption><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th scope="col">Project / period</th><th scope="col">Target output</th><th scope="col">Actual output</th><th scope="col">Remarks</th></tr></thead><tbody>
        @forelse ($records as $record)
            <tr><td data-label="Project / period"><p class="break-words font-semibold">{{ $record->project_title }}</p><p class="mt-1 text-slate-500">{{ $record->quarter_name }} · {{ $record->year }}</p>@if($record->project_archived)<div class="mt-2">@include('shared.partials.badge', ['label' => 'Archived project'])</div>@endif</td><td data-label="Target output" class="tabular-nums">{{ $record->target_output !== null ? number_format($record->target_output, 2) : 'Not recorded' }}</td><td data-label="Actual output" class="font-semibold tabular-nums">{{ $record->actual_output !== null ? number_format($record->actual_output, 2) : 'Not recorded' }}</td><td data-label="Remarks" class="whitespace-pre-wrap break-words">{{ $record->remarks ?: 'No remarks recorded.' }}</td></tr>
        @empty <tr><td colspan="4" class="py-12 text-center"><p class="font-semibold">No production records found</p><p class="mt-2 text-slate-500">{{ !empty($filters['search']) || !empty($filters['year']) ? 'No records match these filters. Try resetting the filters.' : 'No production records have been recorded for this association yet.' }}</p></td></tr> @endforelse
        </tbody></table></div><x-management-pagination :records="$records" />
    </section>
</div>
</x-dashboard-layout>
