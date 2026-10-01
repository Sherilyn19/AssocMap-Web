<section class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5" aria-labelledby="project-trainings">
    <h2 id="project-trainings" class="font-semibold">Related training details</h2>
    <p class="text-sm text-slate-600">Trainings for this association and program component. These records are association activities; no direct project assignment is recorded.</p>
    @forelse($relatedTrainings as $training)
        <article class="rounded-lg border border-slate-200 p-4">
            <h3 class="break-words font-semibold"><a class="underline" href="{{ route('trainings.show', $training) }}">{{ $training->title }}</a></h3>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach(['Training type' => $training->training_type, 'Program component' => $training->programComponent?->name, 'From date' => $training->date_conducted?->format('M d, Y'), 'To date' => $training->end_date?->format('M d, Y'), 'Stage' => \App\Models\Training::STAGES[$training->stage] ?? 'Not recorded', 'Participants' => $training->participants_count, 'Venue' => $training->venue, 'Conducted by' => $training->conducted_by, 'Record' => $training->is_archived ? 'Archived' : 'Active'] as $label => $value)
                    <div class="min-w-0"><dt class="text-xs text-slate-600">{{ $label }}</dt><dd class="break-words">{{ $value ?? 'Not recorded' }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-3 whitespace-pre-line break-words text-sm">{{ $training->remarks ?: 'No remarks.' }}</p>
            <a class="mt-3 inline-block text-sm underline" href="{{ route('trainings.show', $training) }}">View attendance and training details</a>
        </article>
    @empty
        <p class="py-4 text-sm text-slate-600">No training records match this association and program component.</p>
    @endforelse
    {{ $relatedTrainings->links() }}
</section>
