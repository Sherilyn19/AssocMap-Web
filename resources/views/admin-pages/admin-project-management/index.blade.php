{{-- Summary cards describe the whole register. List filters affect Project Records only; card URLs preserve the list position. --}}
<x-dashboard-layout title="Project Management" topbar-title="Administration">
<div data-pm-page class="pm-page space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Project Management</h1>
            <p class="mt-1 text-sm leading-6 text-slate-600">Manage livelihood projects and their materials.</p>
        </div>
        <a class="pm-primary self-start shrink-0 sm:self-auto" href="{{ route('projects.create') }}">
            + Create Project
        </a>
    </header>
    @include('admin-pages.admin-project-management.partials.feedback')
    <section aria-label="Project summary — all records, independent of filters" class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach(['total' => 'Total Projects', 'planned' => 'Planned', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'archived' => 'Archived'] as $key => $label)
            <a id="summary-card-{{ $key }}" class="pm-summary rounded-xl border border-slate-200 bg-white p-4 hover:border-assocmap-primary"
               href="{{ route('projects.index', array_merge($filters, ['summary' => $key, 'page' => $projects->currentPage()])) }}#project-summary"
               aria-haspopup="dialog" aria-label="{{ $label }}: {{ $summary[$key] }}. View detailed records.">
                <span class="block text-sm font-semibold text-slate-700">{{ $label }}</span>
                <span class="mt-1 block text-2xl font-bold tabular-nums">{{ $summary[$key] }}</span>
                <span class="block text-xs text-slate-600">{{ $key === 'total' ? 'Active + archived' : ($key === 'archived' ? 'Historical records' : 'Active records only') }}</span>
                <span class="mt-2 block text-xs font-semibold text-assocmap-primary">View details →</span>
            </a>
        @endforeach
    </section>
    <section class="rounded-xl border border-slate-200 bg-white" aria-labelledby="project-records-title">
        <div class="space-y-4 p-4">
            <h2 id="project-records-title" class="font-semibold">Project Records</h2>
            <nav aria-label="Project record scope" class="flex flex-wrap gap-2">
                @foreach(['active' => 'Active', 'archived' => 'Archived'] as $scope => $label)
                    <a class="pm-action border {{ $filters['scope'] === $scope ? 'border-assocmap-primary bg-emerald-50 text-assocmap-primary' : 'border-slate-200' }}"
                       @if($filters['scope'] === $scope) aria-current="page" @endif
                       href="{{ route('projects.index', array_merge($filters, ['scope' => $scope])) }}">{{ $label }} ({{ $summary[$scope] }})</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('projects.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <input type="hidden" name="scope" value="{{ $filters['scope'] }}">
                <label class="pm-label">Search projects<input class="pm-input" type="search" name="search" maxlength="255" value="{{ $filters['search'] }}" placeholder="Title, association, or commodity">
</label>
                <label class="pm-label">Project status<select class="pm-input" name="status_id">
<option value="">All statuses</option>@foreach($projectStatuses as $status)<option value="{{ $status->id }}" @selected($filters['status_id'] === (string)$status->id)>{{ $status->status_name }}</option>@endforeach</select>
</label>
                <label class="pm-label">Program Component<select class="pm-input" name="program_component_id">
<option value="">All components</option>@foreach($programComponents as $component)<option value="{{ $component->id }}" @selected($filters['program_component_id'] === (string)$component->id)>{{ $component->name }}</option>@endforeach</select>
</label>
                <label class="pm-label">Sort by<select class="pm-input" name="sort">@foreach(['updated'=>'Recently updated','title'=>'Project title A–Z','date'=>'Implementation — newest first','budget_high'=>'Budget — highest first','budget_low'=>'Budget — lowest first'] as $value=>$label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select>
</label>
                <div class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-4">
<button class="pm-primary" type="submit">Apply Filters</button>
<a class="pm-action border border-slate-300" href="{{ route('projects.index', ['scope'=>$filters['scope']]) }}">Clear</a>
</div>
            </form>
            <p class="text-sm text-slate-600" role="status">Showing {{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }} {{ $filters['scope'] }} projects</p>
        </div>
        @include('admin-pages.admin-project-management.partials.records', ['records'=>$projects, 'emptyMessage'=> $summary[$filters['scope']] === 0 ? 'No '.$filters['scope'].' projects have been recorded.' : 'No projects match these filters. Clear or adjust the filters.'])
        <div class="border-t border-slate-200 p-4">{{ $projects->links() }}</div>
    </section>
    @if($summaryProjects)
        @include('admin-pages.admin-project-management.partials.summary')
    @endif
    @include('admin-pages.admin-project-management.partials.archive-dialog')
</div>
</x-dashboard-layout>
