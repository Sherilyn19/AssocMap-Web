{{-- Keep applied-query context with the results, even while the filter form has unsaved edits. --}}
<header class="px-4 py-4 sm:px-5">
    <h2 id="project-records-title" class="text-lg font-bold text-slate-900">Project Records</h2>
</header>
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/70 px-4 py-3 sm:px-5">
    <p class="shrink-0 text-sm text-slate-600" role="status"><strong class="font-semibold text-slate-900">{{ $projects->total() }}</strong> {{ $filters['scope'] }} {{ Str::plural('project', $projects->total()) }}{{ count($appliedFilters) ? ' matching your selection' : '' }}</p>
    @if(count($appliedFilters))
        <nav aria-label="Applied project filters" class="flex flex-wrap gap-2">
            @foreach($appliedFilters as $key => $label)
                <a class="pm-filter-chip" href="{{ route('projects.index', array_merge($filters, [$key => $key === 'sort' ? 'updated' : ''])) }}" aria-label="Remove {{ $label }}">
                    <span class="max-w-64 truncate" title="{{ $label }}">{{ $label }}</span><span aria-hidden="true">×</span>
                </a>
            @endforeach
        </nav>
    @endif
</div>
