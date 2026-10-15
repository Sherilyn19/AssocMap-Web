@php
    $assistanceActor = app(\App\Services\SessionUserResolver::class)->resolve(request());
    $assistanceRole = $assistanceActor->role?->role_name;

    $canEditAssistance = $assistanceActor->is_active && (
        $assistanceRole === 'System Administrator'
        || (
            $assistanceRole === 'Field Officer'
            && (int) $association->field_officer_id === (int) $assistanceActor->id
        )
    );

    $nextStatus = match ($association->assistance_status) {
        null => 'proposal',
        'proposal' => 'accepted',
        'accepted' => 'terminated',
        default => null,
    };

    $assistancePhrase = $nextStatus
        ? app(\App\Services\AssociationAssistanceService::class)
            ->phrase($association->id, $nextStatus)
        : '';

    // Use existing named routes instead of the missing /officer/members page.
    $applicationsUrl = $assistanceRole === 'System Administrator'
        ? route('members.applications.index', ['association_id' => $association->id])
        : route('membership.index', ['association_id' => $association->id]);

    $previousDate = $association->getRawOriginal('assistance_assessed_on');
@endphp

<section class="rounded-xl border border-slate-200 bg-white p-5 space-y-3"
         data-assistance-section>
    <div class="am-info-line">
        <h3 class="font-semibold">Assistance status</h3>
        <x-info label="About assistance status">
            Program assistance status.
        </x-info>
    </div>

    <p>
        {{ \App\Models\Association::ASSISTANCE_STATUSES[$association->assistance_status] ?? 'Not recorded' }}
    </p>

    @if($association->assistance_status === 'terminated')
        <p class="fo-pill fo-pill-amber" role="status">
            Assistance terminated — association records are read-only
        </p>

        <dl class="grid gap-3 sm:grid-cols-2">
            <div>
                <dt>Assessment date</dt>
                <dd>{{ $association->assistance_assessed_on ?: 'Not recorded' }}</dd>
            </div>
            <div>
                <dt>Recorded on</dt>
                <dd>
                    {{ $association->assistance_terminated_at
                        ? \Carbon\Carbon::parse($association->assistance_terminated_at)
                            ->timezone('Asia/Manila')->format('M d, Y · g:i A')
                        : 'Not recorded' }}
                </dd>
            </div>
            <div>
                <dt>Recorded by</dt>
                <dd>{{ $association->assistanceTerminator?->name ?? 'Not recorded' }}</dd>
            </div>
        </dl>

        <p class="whitespace-pre-line">
            {{ $association->assistance_termination_reason ?: 'Assessment summary not recorded.' }}
        </p>
    @else
        @if($previousDate)
            <p class="text-sm text-slate-600">
                Effective date:
                {{ \Carbon\Carbon::parse($previousDate)->format('M d, Y') }}
            </p>
        @endif

        @if($canEditAssistance && !$association->is_archived && $nextStatus)
            <form method="POST"
                  action="{{ route('associations.assistance.update', $association->id) }}"
                  data-assistance-form
                  data-eligibility-url="{{ route('associations.assistance.eligibility', $association->id) }}"
                  data-association-name="{{ $association->name }}"
                  data-confirmation="{{ $assistancePhrase }}"
                  class="space-y-3">
                @csrf
                @method('PATCH')

                <input type="hidden" name="previous_status"
                       value="{{ $association->assistance_status }}">

                <div class="am-assistance-fields">
                    <fieldset class="am-assistance-options">
                        <legend>Change assistance status</legend>

                        <div class="am-assistance-steps">
                            @foreach(\App\Models\Association::ASSISTANCE_STATUSES as $value => $label)
                                @php
                                    $available = $value === $nextStatus;
                                    $current = $value === $association->assistance_status;
                                    $tipId = 'assistance-tip-'.\Illuminate\Support\Str::uuid();

                                    $explanation = match (true) {
                                        $current => 'Current status.',
                                        $available => 'Available next step.',
                                        $value === 'proposal' => 'Cannot return to Proposal.',
                                        $value === 'accepted' => 'Save Proposal first.',
                                        default => 'Save Accepted first.',
                                    };
                                @endphp

                                @unless($loop->first)
                                    <span class="am-assistance-arrow" aria-hidden="true">→</span>
                                @endunless

                                {{-- The wrapper allows keyboard users to read disabled explanations. --}}
                                <label class="am-assistance-choice"
                                    @if(!$available)
                                        tabindex="0"
                                        aria-describedby="{{ $tipId }}"
                                    @endif>
                                    <input type="radio"
                                        name="assistance_status"
                                        value="{{ $value }}"
                                        aria-describedby="{{ $tipId }}"
                                        required
                                        @disabled(!$available)>

                                    <span class="am-assistance-chip">
                                        {{ $label }}
                                        @if($current)
                                            <small>Current</small>
                                        @endif
                                    </span>

                                    <span id="{{ $tipId }}"
                                        class="am-assistance-tip"
                                        role="tooltip">
                                        {{ $explanation }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- Date stays compact beside the dropdown on wider screens. --}}
                    <label data-assistance-date hidden>
                        <span data-assistance-date-label>Effective date</span>
                        <input type="date"
                               name="assessment_date"
                               max="{{ now('Asia/Manila')->toDateString() }}"
                               @if($previousDate)
                                   min="{{ substr((string) $previousDate, 0, 10) }}"
                               @endif
                               value="{{ old('assessment_date') }}"
                               class="block w-full"
                               disabled>
                    </label>
                </div>

                <div data-assistance-details hidden class="space-y-3">
                    <label class="block">
                        <span data-assistance-reason-label>Reason</span>
                        <textarea name="reason"
                                  maxlength="2000"
                                  rows="3"
                                  class="block w-full"
                                  disabled>{{ old('reason') }}</textarea>
                    </label>

                    <div data-assistance-termination hidden class="space-y-3">
                        <p class="text-sm text-slate-600">
                            Termination is permanent. All records within this association become read-only.
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <a class="underline" href="{{ $applicationsUrl }}">
                                Review applications
                            </a>
                            <a class="underline"
                               href="{{ route('membership.drafts.index', ['association_id' => $association->id]) }}">
                                Review unfinished drafts
                            </a>
                            <a class="underline"
                               href="{{ $assistanceRole === 'System Administrator'
                                   ? url('/admin/trainings')
                                   : url('/officer/trainings') }}">
                                Review training attendance
                            </a>
                        </div>
                    </div>
                </div>

                <div data-assistance-feedback role="status" aria-live="polite"
                     class="space-y-2">
                    @foreach(['assistance_status', 'assessment_date', 'reason', 'assistance_confirmation'] as $field)
                        @error($field)
                            <p class="fo-warning">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>

                <button type="submit"
                        class="am-user-button am-user-button-primary"
                        disabled>
                    Continue
                </button>

                <noscript>
                    <p>This confirmation form requires JavaScript.</p>
                </noscript>
            </form>
        @endif
    @endif
</section>