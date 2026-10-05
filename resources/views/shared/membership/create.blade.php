<x-dashboard-layout title="Register Association Member">
    <div class="fo-coverage space-y-5">
        <a href="{{ route('member.applications') }}" class="fo-action">
            Back to applications
        </a>

        <header class="fo-card p-5">
            <p class="fo-eyebrow">Association representative</p>
            <h1 class="mt-2 text-2xl font-bold">Register Association Member</h1>
            <p class="mt-2">{{ $association->name }}</p>
        </header>

        @include('shared.membership.partials.feedback')

        <form method="POST"
              action="{{ route('membership.applications.store') }}"
              class="fo-card space-y-5 p-5">
            @csrf

            {{-- Existing shared profile validation remains in use. --}}
            @include('shared.membership.partials.profile-fields')

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p>
                    Registration requires the current representative's private
                    passphrase. Successful verification creates an approved
                    application and an official member.
                </p>
            </div>

            <label class="block">
                <span class="block font-semibold">Representative review passphrase</span>
                {{-- Never repopulate a secret using old(). --}}
                <input type="password"
                       name="review_passphrase"
                       required
                       maxlength="72"
                       autocomplete="off"
                       class="mt-2 w-full rounded-lg border border-slate-300 p-3">

                @error('review_passphrase')
                    <span class="text-sm text-red-700">{{ $message }}</span>
                @enderror
            </label>

            <button type="submit" class="fo-action am-button-green">
                Verify and register member
            </button>
        </form>
    </div>
</x-dashboard-layout>