@php
    $total = (int) $attendance->sum();
    $present = (int) $attendance->get('Present', 0);
    $absent = (int) $attendance->get('Absent', 0);
    $recorded = $present + $absent;
    $pending = $total - $recorded;
    $percentage = $total ? (int) round($recorded / $total * 100) : 0;
    $today = \App\Support\TrainingCalendar::today();
    $schedule = ! $training->date_conducted ? 'Not scheduled' : ($training->date_conducted->toDateString() > $today ? 'Upcoming' : ($training->end_date && $training->end_date->toDateString() < $today ? 'Schedule ended' : ($training->end_date ? 'Within scheduled dates' : 'Start date reached')));
@endphp
<div class="am-training-workspace space-y-6">
    <header class="am-training-hero">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $training->association->name }} · Training #{{ $training->id }}</p>
        <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ $training->title }}</h2>
        <div class="mt-3 flex flex-wrap items-center gap-2"><span class="text-sm text-slate-500">Training purpose</span>@include('shared.trainings.category') @if($training->is_archived) @include('shared.partials.badge', ['label' => 'Archived']) @endif</div>
        <p class="mt-2 text-xs leading-5 text-slate-500">Shows whether this training supports proposal preparation, an accepted proposal, or project termination.</p>
    </header>
    <section class="am-training-panel" aria-label="Training schedule">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">Training schedule</h3><span class="am-training-category">{{ $schedule }}</span></div>
        @include('shared.trainings.dates', ['compact' => false])
        {{-- Only the FO view uses the newly approved schedule-editing rule. --}}
        <p class="mt-3 text-xs text-slate-500">
            @if(session('auth_user.role_name') === 'Field Officer')
                Dates may change only before participant registration starts.
            @else
                Dates are read-only.
            @endif
            A schedule ending does not confirm training completion.
        </p>
    </section>
    <section class="am-training-panel" aria-label="Attendance progress">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold">Attendance progress</h3><p class="mt-1 text-sm text-slate-500">{{ $recorded }} of {{ $total }} attendance records finalized</p></div><strong data-count-up class="text-2xl tabular-nums text-emerald-700">{{ $total ? $percentage.'%' : '—' }}</strong></div>
        <progress class="am-project-progress mt-4" value="{{ $percentage }}" max="100" aria-label="Attendance recorded">{{ $percentage }}%</progress>
        <dl class="am-training-metrics">
            @foreach (['Registered' => $total, 'Present' => $present, 'Absent' => $absent, 'Pending' => $pending] as $label => $number)
            <div><dt>{{ $label }}</dt><dd data-count-up>{{ $number }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-3 text-xs leading-5 text-slate-500">{{ $total ? 'Progress counts participants marked Present or Absent. It does not measure training completion.' : 'No participants registered yet. Progress will appear when attendance is recorded.' }}</p>
    </section>
    <section class="am-training-panel"><h3 class="font-semibold">Training information</h3>
        <dl class="am-training-card__facts">
        @foreach (['Training type' => $training->training_type, 'Program component' => $training->programComponent?->name, 'Venue' => $training->venue, 'Facilitator' => $training->conducted_by] as $label => $value)
            <div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>
        @endforeach
        </dl>
        <div class="am-training-card__notes"><h4 class="text-xs font-medium text-slate-500">Remarks</h4><p>{{ $training->remarks ?: 'No remarks recorded.' }}</p></div>
    </section>
    <section class="am-training-panel">
        <h3 class="font-semibold">Association projects <span class="text-slate-500">({{ $projects->count() }})</span></h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">Projects of {{ $training->association->name }}. Open a project to view its details here. These projects are not individually assigned to this training.</p>
        <div class="am-training-table-wrap mt-4" tabindex="0" role="region" aria-label="Association projects">
            <table class="am-training-table"><thead><tr><th>Project</th><th>Status</th><th>Implementation</th><th><span class="sr-only">Details</span></th></tr></thead><tbody>
            @forelse ($projects as $project)
                <tr><td><strong>{{ $project->title }}</strong><span class="am-training-table__sub">{{ $project->commodity_type ?: 'Commodity not provided' }} · {{ $project->programComponent?->name ?? 'Component not provided' }}</span></td><td>@include('shared.partials.badge', ['label' => $project->status?->status_name]) @if($project->is_archived)<span class="am-training-table__sub">Archived</span>@endif</td><td>{{ $project->implementation_date?->format('M d, Y') ?? 'Not provided' }}</td><td><a data-project-open class="am-user-button am-user-button-secondary" href="{{ route(($readOnly ?? false) ? 'member.projects.show' : 'officer.projects.show', $project) }}">View project<span class="sr-only">: {{ $project->title }}</span></a></td></tr>
            @empty
                <tr><td colspan="4">No projects recorded for this association.</td></tr>
            @endforelse
            </tbody></table>
        </div>
    </section>
</div>
