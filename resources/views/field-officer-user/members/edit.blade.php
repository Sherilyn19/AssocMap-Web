<x-dashboard-layout title="Manage Member">
    <div class="fo-coverage space-y-5" data-member-editor>
        <a href="{{ route('membership.members.show', $member) }}"
            class="fo-action"
            data-panel-fallback>
                Back to member details
        </a>

        <header class="fo-card p-5">
            <p class="fo-eyebrow">Official member</p>
            <h1 class="mt-2 text-2xl font-bold">
                {{ $member->first_name }} {{ $member->middle_name }} {{ $member->last_name }}
            </h1>
            <p class="mt-2">{{ $member->association?->name }}</p>
        </header>

        @include('shared.membership.partials.feedback')

        <form method="POST"
            action="{{ route('officer.members.update', $member) }}"
            class="fo-card space-y-4 p-5"
            data-member-form
            data-track-changes>
            @csrf
            @method('PUT')

            {{-- Identity, role, registration date, and association remain unchanged. --}}
            <label class="block">
                <span class="block font-semibold">Contact number</span>
                @php($contact = old('contact_number', $member->contact_number))
                <input name="contact_number"
                       type="tel"
                       maxlength="50"
                       value="{{ is_scalar($contact) ? $contact : '' }}"
                       class="mt-2 w-full rounded-lg border border-slate-300 p-3">

                @error('contact_number')
                    <span class="text-sm text-red-700">{{ $message }}</span>
                @enderror
            </label>

            <button class="fo-action am-button-green">Save contact number</button>
        </form>

        @if($canArchive)
            <details class="fo-card p-5">
                <summary class="cursor-pointer font-semibold">Archive member</summary>

                <form method="POST"
                        action="{{ route('officer.members.archive', $member) }}"
                        class="mt-4 space-y-4"
                        data-member-form
                        data-close-after-save
                        data-list-tab="members">
                    @csrf
                    @method('PATCH')

                    <p>Archiving removes this member from the current register and preserves history.</p>

                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>I confirm that this member should be archived.</span>
                    </label>

                    @error('confirm')
                        <p class="text-sm text-red-700">{{ $message }}</p>
                    @enderror

                    {{-- Amber highlights the archive action. --}}
                    <button type="submit" class="fo-action am-button-warning">
                        Confirm archive
                    </button>
                </form>
            </details>
        @else
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                The current association representative cannot be archived.
            </p>
        @endif
    </div>
</x-dashboard-layout>