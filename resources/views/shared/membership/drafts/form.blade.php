@php
    $existing = $draft->exists;
    $profile = $draft->profile ?? [];

    // Reject malformed old input when placing values back into HTML controls.
    $value = function (string $field) use ($profile): string {
        $result = old($field, $profile[$field] ?? '');
        return is_scalar($result) ? (string) $result : '';
    };
@endphp

<x-dashboard-layout title="Prospective Member Draft">
    <div class="fo-coverage space-y-5"
     data-record-content
     data-record-title="{{ $existing ? 'Member draft' : 'Create member draft' }}">
        {{-- Hidden inside the dialog because the dialog already provides a Close button. --}}
        <a href="{{ session('auth_user.role_name') === 'Field Officer'
                ? route('membership.index', ['tab' => 'drafts'])
                : route('membership.drafts.index') }}"
        class="fo-action"
        data-panel-fallback>
            Back to drafts
        </a>

        <header class="fo-card p-5">
            <p class="fo-eyebrow">Prospective member</p>
            <h1 class="mt-2 text-2xl font-bold">
                {{ $existing ? 'Draft details' : 'Create draft' }}
            </h1>

            @if($existing)
                <p class="mt-2">{{ $draft->association?->name }}</p>
                <span class="fo-pill fo-pill-slate mt-3">
                    {{ ucfirst($draft->state) }}
                </span>
            @endif
        </header>

        @include('shared.membership.partials.feedback')

        @if($errors->any())
            <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="font-semibold">Check these fields:</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" data-member-form data-track-changes data-list-tab="drafts"
              action="{{ $existing
                  ? route('membership.drafts.update', $draft)
                  : route('membership.drafts.store') }}"
              class="fo-card space-y-5 p-5">
            @csrf
            @if($existing)
                @method('PUT')
                {{-- Use the loaded revision, never an old revision from a failed form. --}}
                <input type="hidden" name="revision" value="{{ $draft->revision }}">
            @endif

            <fieldset @disabled(!$canEdit) class="space-y-5">
                @unless($existing)
                    <label class="block">
                        <span class="block font-semibold">Assigned association</span>
                        <select name="association_id" required
                                class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                            <option value="">Select association</option>
                            @foreach($associations as $association)
                                <option value="{{ $association->id }}"
                                        @selected(old('association_id') == $association->id)>
                                    {{ $association->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endunless

                {{-- Drafts allow incomplete profiles. Submission validates all required fields. --}}
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach([
                        ['first_name', 'First name', 'text', 255],
                        ['middle_name', 'Middle name', 'text', 255],
                        ['last_name', 'Last name', 'text', 255],
                        ['birthday', 'Birthday', 'date', 10],
                        ['contact_number', 'Contact number', 'text', 50],
                    ] as [$field, $label, $type, $maximum])
                        <label>
                            <span class="block font-semibold">{{ $label }}</span>
                            <input name="{{ $field }}"
                                   type="{{ $type }}"
                                   value="{{ $value($field) }}"
                                   maxlength="{{ $maximum }}"
                                   @if($type === 'date') max="{{ now()->toDateString() }}" @endif
                                   class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                        </label>
                    @endforeach

                    @php
                    // Reuse the values already present in the project's demo seeder.
                    $beneficiaryOptions = [
                        'Rehistradong Mangingisda',
                        'Women Fisherfolk',
                        'Youth Fisherfolk',
                    ];

                    $selectedBeneficiary = $value('beneficiary_type');

                    // Preserve an existing draft value even if it is outside the sample options.
                    if (
                        $selectedBeneficiary !== ''
                        && !in_array($selectedBeneficiary, $beneficiaryOptions, true)
                    ) {
                        $beneficiaryOptions[] = $selectedBeneficiary;
                    }
                @endphp

                <label>
                    <span class="block font-semibold">Beneficiary type</span>

                    {{-- Drafts may remain incomplete until the officer is ready to submit. --}}
                    <select name="beneficiary_type"
                            class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                        <option value="">Not yet selected</option>

                        @foreach($beneficiaryOptions as $beneficiary)
                            <option value="{{ $beneficiary }}"
                                    @selected($selectedBeneficiary === $beneficiary)>
                                {{ $beneficiary }}
                            </option>
                        @endforeach
                    </select>
                </label>

                    <label>
                        <span class="block font-semibold">Sex</span>
                        <select name="sex_id"
                                class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                            <option value="">Not yet selected</option>
                            @foreach($sexOptions as $sex)
                                <option value="{{ $sex->id }}"
                                        @selected($value('sex_id') == $sex->id)>
                                    {{ $sex->sex_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="block font-semibold">Address</span>
                        <textarea name="address" rows="3" maxlength="1000"
                                  class="mt-2 w-full rounded-lg border border-slate-300 p-3">{{ $value('address') }}</textarea>
                    </label>
                </div>

                @if($canEdit)
                    <button class="fo-action am-button-green">Save draft</button>
                @endif
            </fieldset>
        </form>

        @if($existing && $canEdit)
            <section class="fo-card space-y-4 p-5">
                <h2 class="font-semibold">Submit saved draft</h2>
                <p>Save your changes above before submitting.</p>

                @if($blockReason)
                    <p role="status"
                       class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        {{ $blockReason }}
                    </p>
                @endif

                {{-- Submit within the workspace and refresh the Applications table afterward. --}}
                <form method="POST"
                    action="{{ route('membership.drafts.submit', $draft) }}"
                    data-member-form
                    data-list-tab="applications"
                    class="space-y-3">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $draft->revision }}">

                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="confirm" value="1" required
                               @disabled((bool) $blockReason)>
                        <span>I confirm that the saved profile is ready for Field Officer review.</span>
                    </label>

                    <button class="fo-action am-button-green disabled:opacity-50"
                            @disabled((bool) $blockReason)>
                        Submit application
                    </button>
                </form>
            </section>

            <details class="fo-card p-5">
                <summary class="cursor-pointer font-semibold">Cancel this draft</summary>

                <form method="POST" action="{{ route('membership.drafts.cancel', $draft) }}"
                    data-member-form
                    data-list-tab="drafts"
                    class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="revision" value="{{ $draft->revision }}">

                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>Cancel this draft permanently. Its history will remain stored.</span>
                    </label>

                    <button class="fo-action">Confirm cancellation</button>
                </form>
            </details>
        @endif

        @if($existing && $draft->application_id)
            {{-- Open the submitted application inside the record modal. --}}
            <a href="{{ route('membership.applications.show', $draft->application_id) }}"
                class="fo-action am-button-green"
                data-record-open
                data-record-title="Application details">
                    View submitted application
            </a>
        @endif
    </div>
</x-dashboard-layout>