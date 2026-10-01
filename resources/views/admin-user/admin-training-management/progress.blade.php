<section class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Training progress">
    <h2 class="font-semibold">Training progress</h2>
    <p class="mt-1 text-sm text-slate-600">Current stage: {{ \App\Models\Training::STAGES[$training->stage] ?? 'Not recorded' }}. Only one stage applies at a time.</p>
    <ol class="mt-3 flex flex-wrap gap-3">
        @foreach(\App\Models\Training::STAGES as $stage => $label)
            <li class="rounded-lg border px-3 py-2 text-sm {{ $training->stage === $stage ? 'border-blue-300 bg-blue-50 font-semibold text-blue-900' : 'border-slate-200 text-slate-600' }}" @if($training->stage === $stage) aria-current="step" @endif>
                {{ $label }} @if($training->stage === $stage)<span class="sr-only">(current)</span>@endif
            </li>
        @endforeach
    </ol>
</section>
