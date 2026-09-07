{{-- Summary cards describe the whole register. List filters affect Project Records only; card URLs preserve the list position. --}}
<x-dashboard-layout title="Project Management" topbar-title="Project Management">
<div data-pm-page data-management-register class="pm-page mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">BFAR SAAD Phase II</span>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Project Management</h1>
            <p class="mt-1 text-sm leading-6 text-slate-600">Manage livelihood projects and their materials.</p>
        </div>
        <a class="pm-primary self-start shrink-0 sm:self-auto" href="{{ route('projects.create') }}">
            + Create Project
        </a>
    </header>
    @include('admin-pages.admin-project-management.partials.feedback')
    <section aria-label="Project summary — all records, independent of filters" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach(['total' => 'Total Projects', 'planned' => 'Planned', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'archived' => 'Archived'] as $key => $label)
            <a id="summary-card-{{ $key }}" class="pm-summary rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
               href="{{ route('projects.index', array_merge($filters, ['summary' => $key, 'page' => $projects->currentPage()])) }}#project-summary"
               aria-haspopup="dialog" aria-label="{{ $label }}: {{ $summary[$key] }}. View detailed records.">
                <span class="block text-sm font-medium text-slate-600">{{ $label }}</span>
                <span class="mt-2 block text-3xl font-bold tabular-nums">{{ $summary[$key] }}</span>
                <span class="block text-xs text-slate-600">{{ $key === 'total' ? 'Active + archived' : ($key === 'archived' ? 'Historical records' : 'Active records only') }}</span>

            </a>
        @endforeach
    </section>
    {{-- Prepare shared presentation values in the parent: sibling includes do not
         share variables assigned inside each other. No extra database queries are needed. --}}
{{-- Applied chips describe the server-rendered results, not unsaved form edits.
     Removing a chip preserves the other filters and restarts pagination at page one. --}}
@php
    $sortOptions = ['updated' => 'Recently updated', 'title' => 'Project title A–Z', 'date' => 'Implementation — newest first', 'budget_high' => 'Budget — highest first', 'budget_low' => 'Budget — lowest first'];
    $appliedFilters = [];
    if ($filters['search'] !== '') $appliedFilters['search'] = 'Search: '.$filters['search'];
    if ($filters['status_id'] !== '') $appliedFilters['status_id'] = 'Status: '.($projectStatuses->firstWhere('id', $filters['status_id'])?->status_name ?? 'Unavailable');
    if ($filters['program_component_id'] !== '') $appliedFilters['program_component_id'] = 'Component: '.($programComponents->firstWhere('id', $filters['program_component_id'])?->name ?? 'Unavailable');
    if ($filters['sort'] !== 'updated') $appliedFilters['sort'] = 'Sort: '.$sortOptions[$filters['sort']];
@endphp
    {{-- Separate controls from results; the page's space-y-6 keeps both cards 24px apart.
         Both sections inherit the existing scroll-reveal animation. --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="project-filters-title">
        @include('admin-pages.admin-project-management.partials.filters')
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="project-records-title">
        @include('admin-pages.admin-project-management.partials.results-header')
        @include('admin-pages.admin-project-management.partials.records', ['records'=>$projects, 'emptyMessage'=> $summary[$filters['scope']] === 0 ? 'No '.$filters['scope'].' projects have been recorded.' : 'No projects match these filters. Clear or adjust the filters.'])
        <x-management-pagination :records="$projects" />
    </section>
    @if($summaryProjects)
        @include('admin-pages.admin-project-management.partials.summary')
    @endif
    @include('admin-pages.admin-project-management.partials.archive-dialog')
</div>
</x-dashboard-layout>
