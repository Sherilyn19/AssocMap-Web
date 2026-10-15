@php
    $assistanceActor = app(\App\Services\SessionUserResolver::class)->resolve(request());
    $assistanceRole = $assistanceActor->role?->role_name;
    $canEditAssistance = $assistanceActor->is_active && (
        $assistanceRole === 'System Administrator'
        || ($assistanceRole === 'Field Officer' && (int) $association->field_officer_id === (int) $assistanceActor->id)
    );
    $assistancePhrase = 'TERMINATE ASSOC-'.str_pad((string) $association->id, 6, '0', STR_PAD_LEFT);
@endphp
<section class="rounded-xl border border-slate-200 bg-white p-5 space-y-3" data-assistance-section>
    <div class="am-info-line">
        <h3 class="font-semibold">Assistance status</h3>
        <x-info label="About assistance status">Program assistance only; separate from training completion and active/inactive status.</x-info>
    </div>
    <p>{{ \App\Models\Association::ASSISTANCE_STATUSES[$association->assistance_status] ?? 'Not recorded' }}</p>
    @if($association->assistance_status === 'terminated')
        <p class="fo-pill fo-pill-amber" role="status">Assistance terminated — association records are read-only</p>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Assessment date</dt><dd>{{ $association->assistance_assessed_on ?: 'Not recorded' }}</dd></div>
            <div><dt>Recorded on</dt><dd>{{ $association->assistance_terminated_at ? \Carbon\Carbon::parse($association->assistance_terminated_at)->timezone('Asia/Manila')->format('M d, Y · g:i A') : 'Not recorded' }}</dd></div>
            <div><dt>Recorded by</dt><dd>{{ $association->assistanceTerminator?->name ?? 'Not recorded' }}</dd></div>
        </dl>
        <p class="whitespace-pre-line">{{ $association->assistance_termination_reason ?: 'Assessment summary not recorded.' }}</p>
    @elseif($canEditAssistance && !$association->is_archived)
        <form method="POST" action="{{ route('associations.assistance.update', $association->id) }}"
              data-assistance-form data-eligibility-url="{{ route('associations.assistance.eligibility', $association->id) }}"
              data-association-name="{{ $association->name }}" data-confirmation="{{ $assistancePhrase }}" class="space-y-3">
            @csrf @method('PATCH')
            <input type="hidden" name="previous_status" value="{{ $association->assistance_status }}">
            <label class="block">Assistance status
                <select name="assistance_status" required class="block w-full">
                    <option value="">Select status</option>
                    @foreach(\App\Models\Association::ASSISTANCE_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(old('assistance_status', $association->assistance_status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">Assessment date <span class="text-sm">(required for termination)</span>
                <input type="date" name="assessment_date" max="{{ now('Asia/Manila')->toDateString() }}" value="{{ old('assessment_date') }}" class="block w-full">
            </label>
            <label class="block"><span data-assistance-reason-label>Assessment summary / reason for change</span>
                <textarea name="reason" required maxlength="2000" rows="3" class="block w-full">{{ old('reason') }}</textarea>
            </label>
            <p class="text-sm text-slate-600">Termination is permanent. All records within this association become read-only.</p>
            <noscript>
                <label class="block">For termination, type {{ $assistancePhrase }}
                    <input name="assistance_confirmation" autocomplete="off" class="block w-full">
                </label>
            </noscript>
            <div data-assistance-feedback role="status" aria-live="polite" class="space-y-2">
                @foreach(['assistance_status', 'assessment_date', 'reason', 'assistance_confirmation'] as $field)
                    @error($field)<p class="fo-warning">{{ $message }}</p>@enderror
                @endforeach
            </div>
            <div data-assistance-links hidden class="flex flex-wrap gap-3">
                <a class="underline" href="{{ $assistanceRole === 'System Administrator' ? url('/admin/members') : url('/officer/members') }}">Review applications</a>
                <a class="underline" href="{{ url('/membership/drafts') }}">Review unfinished drafts</a>
                <a class="underline" href="{{ $assistanceRole === 'System Administrator' ? url('/admin/trainings') : url('/officer/trainings') }}">Review training attendance</a>
            </div>
            <button type="submit" class="am-user-button am-user-button-primary">Continue</button>
        </form>
    @endif
</section>
