{{-- Closing restores the original list filters/page. View matching records intentionally starts a new list using only the selected card group. --}}
@php
    $summaryTitle = $summaryKey === 'total' ? 'All Projects' : ucfirst($summaryKey).' Projects';
    $closeUrl = route('projects.index', array_merge($filters, ['page'=>$projects->currentPage()]));
    $matchFilters = ['scope'=>$summaryKey === 'archived' ? 'archived' : 'active'];
    if(in_array($summaryKey, ['planned','ongoing','completed'], true)) {
        $matchFilters['status_id'] = $projectStatuses->firstWhere('status_name', ucfirst($summaryKey))?->id;
    }
@endphp
<section id="project-summary" data-pm-summary="{{ $summaryKey }}" data-close-url="{{ $closeUrl }}" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6" aria-labelledby="summary-title">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
<h2 id="summary-title" tabindex="-1" class="text-xl font-bold">{{ $summaryTitle }}</h2>
<p class="mt-1 text-sm text-slate-600">{{ $summaryKey === 'total' ? 'All active and archived records.' : ($summaryKey === 'archived' ? 'Archived records with their retained project status.' : 'Active records only.') }} Independent of the main-list filters.</p>
</div>
        <a href="{{ $closeUrl }}#summary-card-{{ $summaryKey }}" class="pm-action border border-slate-300" data-pm-close>Close details</a>
    </header>
    @if($summaryKey === 'total')
        <dl class="my-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach(['active'=>'Active records','archived'=>'Archived records','planned'=>'Active · Planned','ongoing'=>'Active · Ongoing','completed'=>'Active · Completed'] as $key=>$label)
                <div class="rounded-lg bg-slate-50 p-3">
<dt class="text-xs text-slate-600">{{ $label }}</dt>
<dd class="text-lg font-semibold tabular-nums">{{ $summary[$key] }}</dd>
</div>
            @endforeach
            @if($summary['other'])<div>
<dt>Active · Other / unrecorded status</dt>
<dd>{{ $summary['other'] }}</dd>
</div>@endif
        </dl>
        <div class="my-3 flex flex-wrap gap-2">
<a class="pm-action border" href="{{ route('projects.index', ['scope'=>'active']) }}">View active records</a>
<a class="pm-action border" href="{{ route('projects.index', ['scope'=>'archived']) }}">View archived records</a>
</div>
    @else
        <a class="pm-action my-3 border border-slate-300" href="{{ route('projects.index', $matchFilters) }}">View matching records</a>
    @endif
    <p class="mb-3 text-sm text-slate-600">Showing {{ $summaryProjects->firstItem() ?? 0 }}–{{ $summaryProjects->lastItem() ?? 0 }} of {{ $summaryProjects->total() }} projects</p>
    @include('admin-pages.admin-project-management.partials.records', ['records'=>$summaryProjects, 'readOnly'=>true, 'caption'=>$summaryTitle])
    <div class="mt-4">{{ $summaryProjects->fragment('project-summary')->links() }}</div>
</section>
