<x-dashboard-layout title="Association Projects" topbar-title="Association Projects">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => 'Association Projects', 'description' => 'View your association’s livelihood projects, training progress, implementation details and materials.'])
    @include('association-member-user.partials.filters', ['label' => 'Projects', 'options' => ['' => 'All current projects', 'Planned' => 'Planned', 'Ongoing' => 'Ongoing', 'Completed' => 'Completed', 'Archived' => 'Archived'], 'reset' => route('member.projects')])
    <section aria-label="Project records" class="space-y-4">
        <h2 class="text-lg font-semibold">Project records <span class="text-slate-500">({{ $projects->total() }})</span></h2>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $project)
            <article class="flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                <div>@include('shared.partials.badge', ['label' => $project->is_archived ? 'Archived' : $project->status?->status_name])</div>
                <h3 class="mt-4 break-words text-lg font-semibold">{{ $project->title }}</h3>
                <dl class="my-4 grid gap-3 text-sm"><div><dt class="text-slate-500">Commodity</dt><dd class="mt-1 break-words">{{ $project->commodity_type ?: 'Not recorded' }}</dd></div><div><dt class="text-slate-500">Program component</dt><dd class="mt-1">{{ $project->programComponent?->name ?? 'Not recorded' }}</dd></div><div><dt class="text-slate-500">Implementation date</dt><dd class="mt-1">{{ $project->implementation_date?->format('M j, Y') ?? 'Not recorded' }}</dd></div></dl>
                <a data-project-open class="am-user-button am-user-button-secondary mt-auto self-start" href="{{ route('member.projects.show', $project) }}">View project<span class="sr-only">: {{ $project->title }}</span> <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-12 text-center md:col-span-2 xl:col-span-3"><h3 class="font-semibold">No projects found</h3><p class="mt-2 text-sm text-slate-500">{{ $filters['search'] || $filters['status'] ? 'No projects match these filters. Try another search or reset the filters.' : 'No current projects have been recorded for this association yet.' }}</p></div>
        @endforelse
        </div>
        <x-management-pagination :records="$projects" />
    </section>
</div>
@include('shared.projects.dialog')
</x-dashboard-layout>
