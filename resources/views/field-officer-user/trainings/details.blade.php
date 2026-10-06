@php
    $editable = ! $training->is_archived && ! $training->association->is_archived;
@endphp

<div class="fo-training-ui space-y-5">
    {{-- Related actions stay together rather than spreading across the modal. --}}
    <div class="flex flex-wrap items-center gap-3">
        <a data-training-panel data-panel-title="Training attendance"
           href="{{ route('officer.trainings.panel', [$training, 'attendance']) }}"
           class="fo-action am-button-green">
            View attendance
        </a>

        @if($editable)
            <a data-training-panel data-panel-title="Edit training"
               href="{{ route('officer.trainings.panel', [$training, 'edit']) }}"
               class="fo-action">
                Edit training
            </a>

            <a data-training-panel data-panel-title="Archive training"
               href="{{ route('officer.trainings.panel', [$training, 'archive']) }}"
               class="fo-action fo-training-warning">
                Archive
            </a>
        @endif
    </div>

    <section class="am-training-panel">
        <h3 class="font-semibold">Approval and budget</h3>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <span class="fo-pill {{ $training->external_approval_recorded_at ? 'fo-pill-teal' : '' }}">
                {{ $training->external_approval_recorded_at
                    ? 'Approved externally'
                    : 'Approval not recorded' }}
            </span>

            <span>
                Training cost:
                <strong>
                    {{ $training->training_cost === null
                        ? 'Not recorded'
                        : 'PHP '.number_format((float) $training->training_cost, 2) }}
                </strong>
            </span>
        </div>

        @if($training->external_approval_recorded_at)
            <p class="mt-2 text-sm text-slate-600">
                External approval confirmation recorded
                {{ $training->external_approval_recorded_at->format('M d, Y g:i A') }}.
            </p>
        @endif
    </section>

    @include('shared.trainings.details', ['readOnly' => false])
</div>