<x-dashboard-layout title="Training Details" topbar-title="Training Details">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <a class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-800 underline" href="{{ route('member.trainings') }}">← Back to trainings</a>
    @include('association-member-user.partials.header', ['heading' => $training->title, 'description' => 'Training information and recorded attendance totals for your association.'])
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Training details</h2>@include('shared.partials.badge', ['label' => $training->is_archived ? 'Archived' : (\App\Models\Training::STAGES[$training->stage] ?? 'Stage not recorded')])</div>
        <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Training type' => $training->training_type, 'Program component' => $training->programComponent?->name, 'Venue' => $training->venue, 'From date' => $training->date_conducted?->format('F j, Y'), 'To date' => $training->end_date?->format('F j, Y'), 'Conducted by' => $training->conducted_by] as $label => $value)
                <div class="min-w-0"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-2 break-words font-medium">{{ $value ?: 'Not recorded' }}</dd></div>
            @endforeach
        </dl><h3 class="mt-6 text-sm text-slate-500">Remarks</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6">{{ $training->remarks ?: 'No remarks recorded.' }}</p>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-semibold">Recorded attendance</h2>
        @if ($attendance->isEmpty())<p class="mt-4 text-sm text-slate-500">No attendance entries have been recorded for this training yet.</p>
        @else <dl class="mt-5 grid gap-4 sm:grid-cols-3">@foreach ($attendance as $status => $total)<div class="rounded-lg border border-slate-200 p-4"><dt>@include('shared.partials.badge', ['label' => $status])</dt><dd class="mt-3 text-2xl font-semibold tabular-nums">{{ $total }}</dd></div>@endforeach</dl>@endif
    </section>
</div>
</x-dashboard-layout>
