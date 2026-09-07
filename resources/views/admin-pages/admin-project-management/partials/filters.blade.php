<div class="pm-filter-panel p-4 sm:p-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="project-filters-title" class="text-lg font-bold text-slate-900">Filter Projects</h2>
            <p class="mt-1 text-sm text-slate-500">Find projects by name, status, or program component.</p>
        </div>
        <nav aria-label="Project record scope" class="inline-flex self-start rounded-xl bg-slate-100 p-1 sm:self-auto">
            @foreach(['active' => 'Active', 'archived' => 'Archived'] as $scope => $label)
                <a class="pm-scope-switch {{ $filters['scope'] === $scope ? 'is-selected' : '' }}"
                   @if($filters['scope'] === $scope) aria-current="page" @endif
                   href="{{ route('projects.index', array_merge($filters, ['scope' => $scope])) }}">
                    {{ $label }} <span class="rounded-md bg-slate-200/60 px-1.5 py-0.5 text-xs tabular-nums">{{ $summary[$scope] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <form method="GET" action="{{ route('projects.index') }}" data-pm-filters class="mt-5 space-y-4">
        <input type="hidden" name="scope" value="{{ $filters['scope'] }}">
        {{-- Search leads the form; secondary controls use a responsive row below it. --}}
        <label class="pm-label" for="project-filter-search">Search projects</label>
        <div class="relative !mt-1.5">
            <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-3.5 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10.5" cy="10.5" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
            <input id="project-filter-search" class="pm-input !mt-0 !min-h-12 !pl-11" type="search" name="search" maxlength="255" value="{{ $filters['search'] }}" placeholder="Search by project title, association, or commodity">
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="pm-label">Project status
                <select class="pm-input" name="status_id">
                    <option value="">All statuses</option>
                    @foreach($projectStatuses as $status)
                        <option value="{{ $status->id }}" @selected($filters['status_id'] === (string) $status->id)>{{ $status->status_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="pm-label">Program component
                <select class="pm-input" name="program_component_id">
                    <option value="">All components</option>
                    @foreach($programComponents as $component)
                        <option value="{{ $component->id }}" @selected($filters['program_component_id'] === (string) $component->id)>{{ $component->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="pm-label sm:col-span-2 lg:col-span-1">Sort by
                <select class="pm-input" name="sort">
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p data-pm-filter-hint class="text-xs text-slate-500" role="status">Choose filters, then apply to update the records.</p>
            <div class="flex flex-wrap items-center gap-2">
                <a class="pm-action" href="{{ route('projects.index', ['scope' => $filters['scope']]) }}">Reset filters</a>
                <button class="pm-primary gap-2" type="submit">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-6 7v6l-4 2v-8Z"/></svg>
                    Apply filters
                </button>
            </div>
        </div>
    </form>
</div>
