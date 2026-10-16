@php
    $canManageActivities = !$project->is_archived && !$project->association->is_archived && $project->association->assistance_status !== 'terminated';
    $completedActivities = $activities->where('state', 'Completed')->count();
    $activityProgress = $activities->count() ? (int) round(100 * $completedActivities / $activities->count()) : 0;
@endphp
<section class="rounded-2xl border border-teal-100 bg-white p-4 sm:p-5" data-project-activities>
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            {{-- Heroicons calendar-days outline. --}}
            <svg class="h-5 w-5 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25m10.5-2.25v2.25M3 9.75h18M5.25 5.25h13.5A2.25 2.25 0 0 1 21 7.5v12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 19.5v-12a2.25 2.25 0 0 1 2.25-2.25Z"/></svg>
            <h3 class="text-base font-semibold">Project activities</h3>
            <x-info>Progress is completed activities divided by listed activities. It does not measure spending, attendance, or production.</x-info>
        </div>
        @if($canManageActivities)<a href="{{ route('officer.projects.activities.create', $project) }}" data-project-manage data-editor-title="Add activity" class="fo-action am-button-green text-sm">＋ Add activity</a>@endif
    </header>
    <div class="my-4 rounded-xl bg-teal-50 p-3">
        <div class="mb-2 flex justify-between text-xs font-medium text-teal-900"><span>{{ $completedActivities }} of {{ $activities->count() }} completed</span><span>{{ $activityProgress }}%</span></div>
        <progress class="am-project-progress w-full" value="{{ $activityProgress }}" max="100" aria-label="Project activities completed">{{ $activityProgress }}%</progress>
        <p class="mt-2 text-xs text-slate-600">Responsible: {{ $project->association->fieldOfficer?->name ?? 'Unassigned' }} @if((int) $project->association->field_officer_id === (int) $actor->id)<span class="ml-1 rounded-full bg-white px-2 py-0.5 text-teal-800">You</span>@endif</p>
    </div>
    <ol class="space-y-3">
        @forelse($activities as $activity)
            <li class="fo-activity-card rounded-xl border border-slate-200 p-3 transition duration-200 hover:border-teal-300 hover:shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold">{{ $activity->title }}</p><p class="mt-1 text-xs text-slate-500">{{ \Carbon\Carbon::parse($activity->starts_on)->format('M d, Y') }} → {{ \Carbon\Carbon::parse($activity->ends_on)->format('M d, Y') }}</p></div>@include('shared.partials.badge', ['label' => $activity->state])</div>
                @if($activity->notes)<p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $activity->notes }}</p>@endif
                <div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs text-slate-500">{{ $project->materials->where('activity_id', $activity->id)->whereNull('archived_at')->count() }} linked current materials</span>@if($canManageActivities)<a href="{{ route('officer.projects.activities.edit', [$project, $activity->id]) }}" data-project-manage data-editor-title="Edit activity" class="text-sm font-medium text-teal-700 hover:underline">Edit activity</a>@endif</div>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">No activities recorded. Add the project's implementation steps to begin its timeline.</li>
        @endforelse
    </ol>
</section>
