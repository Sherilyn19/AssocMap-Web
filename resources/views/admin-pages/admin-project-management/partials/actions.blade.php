{{-- Summary listings pass readOnly to show View only. Archive protection is also enforced on the server. --}}
<div class="flex flex-wrap items-center gap-1">
    <a class="pm-action" href="{{ route('projects.show', $project) }}" aria-label="View {{ $project->title }}">View</a>
    @if(!($readOnly ?? false) && !$project->is_archived)
        <a class="pm-action" href="{{ route('projects.edit', $project) }}" aria-label="Edit {{ $project->title }}">Edit</a>
        <details class="relative" data-pm-menu>
            <summary class="pm-action cursor-pointer" aria-label="More actions for {{ $project->title }}">More</summary>
            <form method="POST" action="{{ route('projects.archive', $project) }}" data-pm-archive data-project-title="{{ $project->title }}"
                  class="absolute right-0 z-20 min-w-40 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                @csrf @method('PATCH')
                <button type="submit" class="pm-action w-full text-red-800">Archive Project</button>
            </form>
        </details>
    @endif
</div>
