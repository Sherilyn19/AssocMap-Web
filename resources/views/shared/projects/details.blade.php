@php
    $activeTrainings = $trainings->where('is_archived', false);
    $registered = $activeTrainings->sum('registered_count');
    $recorded = $activeTrainings->sum('recorded_count');
    $percent = $registered > 0 ? (int) round($recorded / $registered * 100) : 0;
@endphp
<div class="space-y-6 am-project-details">
    {{-- Only the FO view offers these controls; backend scope is checked again. --}}
    @if(session('auth_user.role_name') === 'Field Officer'
        && !$project->is_archived
        && !$project->association->is_archived)
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('officer.projects.edit', $project) }}"
            data-project-manage
            data-editor-title="Manage project"
            class="fo-action am-button-warning">
                Manage project
            </a>

            <a href="{{ route('officer.projects.materials.create', $project) }}"
            data-project-manage
            data-editor-title="Add material"
            class="fo-action am-button-green">
                <span aria-hidden="true">＋</span>
                Add material
            </a>
        </div>
    @endif
<header class="border-b border-slate-200 pb-5">
    <span class="am-officer-eyebrow">Project #{{ $project->id }} · {{ $project->association->name }}</span>
    <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ $project->title }}</h2>
    <div class="mt-3 flex flex-wrap gap-2">@include('shared.partials.badge', ['label' => $project->status?->status_name ?? 'Status not recorded']) @if($project->is_archived) @include('shared.partials.badge', ['label' => 'Archived']) @endif</div>
</header>
<div class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm text-slate-600">Implementation date</p><p class="mt-2 font-semibold">{{ $project->implementation_date?->format('M d, Y') ?? 'Not recorded' }}</p></div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm text-slate-600">Termination date</p><p class="mt-2 font-semibold">{{ $project->terminated_on?->format('M d, Y') ?? 'Not recorded' }}</p></div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm text-slate-600">Material deliveries recorded</p><p class="mt-2 font-semibold">{{ $project->materials->whereNotNull('delivery_date')->count() }} of {{ $project->materials->count() }}</p></div>
</div>
<section class="rounded-xl border border-slate-200 bg-white p-5">
    <h3 class="text-lg font-semibold">Project overview</h3>
    <dl class="mt-4 grid gap-5 sm:grid-cols-2">
    @foreach (['Association' => $project->association->name, 'Commodity' => $project->commodity_type, 'Program component' => $project->programComponent?->name, 'Project proposal acceptance date' => null] as $label => $value)
        <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value ?: 'Not recorded' }}</dd></div>
    @endforeach
    <div class="sm:col-span-2"><dt class="text-sm text-slate-500">Project remarks</dt><dd class="mt-1 whitespace-pre-line">{{ $project->remarks ?: 'No remarks recorded.' }}</dd></div>
    </dl>
    <p class="mt-4 text-xs leading-5 text-slate-500">Project records do not currently store a proposal decision or acceptance date. Training categories do not confirm project approval.</p>
</section>
<section class="rounded-xl border border-slate-200 bg-white p-5">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-lg font-semibold">Association trainings</h3><p class="mt-1 text-sm text-slate-600">{{ $activeTrainings->count() }} active training records · {{ $trainings->where('is_archived', true)->count() }} archived</p></div><span class="rounded-lg bg-emerald-50 px-3 py-2 font-semibold text-emerald-800">{{ $registered ? $percent.'%' : 'No attendance yet' }}</span></div>
    <label class="mt-4 block text-sm font-medium" for="project-training-progress">Attendance recorded</label>
    <progress id="project-training-progress" class="am-project-progress mt-2" value="{{ $percent }}" max="100">{{ $percent }}%</progress>
    <p class="mt-2 text-sm text-slate-600">{{ $recorded }} of {{ $registered }} participant attendance records finalized across active trainings.</p>

        {{-- Keep this explanation for the other users sharing this template. --}}
    @if(session('auth_user.role_name') !== 'Field Officer')
        <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-600">
            Trainings for {{ $project->association->name }}.
            Progress shows attendance recorded, not training completion.
        </p>
    @endif

    <div class="mt-5 space-y-5">
    @foreach (\App\Models\Training::STAGES + ['unknown' => 'Other trainings'] as $stage => $stageLabel)
        @php($stageTrainings = $trainings->filter(fn ($training) => $stage === 'unknown' ? ! array_key_exists($training->stage ?? '', \App\Models\Training::STAGES) : $training->stage === $stage))
        @if($stage !== 'unknown' || $stageTrainings->isNotEmpty())
        <section aria-label="{{ $stageLabel }} trainings"><div class="flex items-center gap-2"><h4 class="font-semibold text-slate-800">{{ $stageLabel }}</h4><span class="text-sm text-slate-500">({{ $stageTrainings->count() }})</span></div>
        <div class="mt-3 space-y-3">
        @forelse ($stageTrainings as $training)
            @php($trainingPercent = $training->registered_count > 0 ? (int) round($training->recorded_count / $training->registered_count * 100) : 0)
            <details class="am-training-card">
                <summary class="am-training-card__summary">
                    <span class="am-training-card__heading">
                        <span class="am-training-card__title">{{ $training->title }}</span>
                        <span class="am-training-card__meta">{{ $training->date_conducted?->format('M d, Y') ?? 'Date not set' }} · {{ $training->training_type ?: 'Training' }}{{ $training->is_archived ? ' · Archived' : '' }}</span>
                    </span>
                    <span class="am-training-card__toggle"><span class="am-training-show">View details</span><span class="am-training-hide">Hide details</span><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></span>
                </summary>
                <div class="am-training-card__body">
                    <div class="am-training-attendance">
                        <div class="am-training-attendance__label"><label><input type="checkbox" disabled @checked($training->registered_count > 0 && $training->recorded_count === $training->registered_count)> Attendance recorded</label><strong>{{ $training->recorded_count }} / {{ $training->registered_count }}</strong></div>
                        <progress class="am-project-progress" aria-label="Attendance recorded for {{ $training->title }}" value="{{ $trainingPercent }}" max="100">{{ $trainingPercent }}%</progress>
                        <div class="am-training-counts"><span><strong>{{ $training->present_count }}</strong> Present</span><span><strong>{{ $training->recorded_count - $training->present_count }}</strong> Absent</span><span><strong>{{ $training->registered_count - $training->recorded_count }}</strong> Pending</span></div>
                    </div>
                    <dl class="am-training-card__facts">
                    @foreach (['Program component' => $training->programComponent?->name, 'Venue' => $training->venue, 'Start date' => $training->date_conducted?->format('M d, Y'), 'End date' => $training->end_date?->format('M d, Y'), 'Facilitator' => $training->conducted_by] as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>
                    @endforeach
                    </dl>
                    @if($training->remarks)
                    <div class="am-training-card__notes"><h5>Remarks</h5><p>{{ $training->remarks }}</p></div>
                    @endif
                </div>
            </details>
        @empty
            <p class="rounded-lg border border-dashed border-slate-200 p-4 text-sm text-slate-500">No training listed.</p>
        @endforelse
        </div></section>
        @endif
    @endforeach
    </div>
</section>
@if($readOnly ?? false)
    @include('association-member-user.partials.project-materials')
@else
    @include('field-officer-user.projects.materials')
@endif
</div>
