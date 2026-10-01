<x-dashboard-layout title="Projects">
<div class="space-y-6">
<header><span class="am-officer-eyebrow">BFAR SAAD Phase II</span><h1>Projects</h1><p class="mt-1 text-sm leading-6 text-slate-600">Review project status, association training progress, and material deliveries in one place.</p></header>
@include('shared.membership.partials.feedback')
@if ($association)
<div class="rounded-xl border border-assocmap-border bg-white p-4"><p class="text-sm text-slate-600">Association context</p><p class="font-semibold">{{ $association->name }}</p><a class="mt-2 inline-block text-sm underline" href="{{ route('officer.projects.index') }}">Show all assigned associations</a></div>
@endif
<form method="GET" class="am-officer-filters"><label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">Search projects<input name="search" value="{{ request('search') }}" placeholder="Search project title" class="mt-2 w-full"></label><input type="hidden" name="association_id" value="{{ $association?->id }}"><button class="am-officer-button">Search</button><a class="am-user-button am-user-button-secondary" href="{{ route('officer.projects.index') }}">Reset</a></form>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="Project records"><div class="border-b border-slate-200 p-5"><h2 class="text-lg font-semibold">Project records</h2><p class="mt-1 text-sm text-slate-600">Records matching your current filters and assignments.</p></div><div tabindex="0" role="region" aria-label="Scrollable records" class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Project</th><th class="p-3">Association</th><th class="p-3">Component</th><th class="p-3">Status</th><th class="p-3">Implementation</th><th class="p-3"><span class="sr-only">Actions</span></th></tr></thead><tbody>
@forelse ($projects as $project)
<tr class="border-t"><td class="p-3"><a data-project-open class="font-semibold underline" href="{{ route('officer.projects.show', $project) }}">{{ $project->title }}</a></td><td class="p-3">{{ $project->association->name }}</td><td class="p-3">{{ $project->programComponent?->name ?? 'Not recorded' }}</td><td class="p-3">@include('shared.partials.badge', ['label' => $project->is_archived ? 'Archived' : ($project->status?->status_name ?? 'Not recorded')])</td><td class="p-3">{{ $project->implementation_date?->format('M d, Y') ?? 'Not recorded' }}</td><td class="p-3"><a data-project-open class="am-user-button am-user-button-secondary whitespace-nowrap" href="{{ route('officer.projects.show', $project) }}">View details<span class="sr-only"> for {{ $project->title }}</span></a></td></tr>
@empty
<tr><td colspan="6" class="p-4">No records found for the selected criteria</td></tr>
@endforelse
</tbody></table></div><x-management-pagination :records="$projects" label="Project records pagination" /></section></div>
@include('shared.projects.dialog')
</x-dashboard-layout>

