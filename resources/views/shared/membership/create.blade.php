<x-dashboard-layout title="Submit Application" topbar-title="Submit Application">
    <div data-member-workspace class="mx-auto max-w-5xl space-y-5 p-4 sm:p-6">
        <a href="{{ route('member.applications') }}"
           class="am-user-button am-user-button-secondary">
            Back to applications
        </a>

        @include('association-member-user.partials.header', [
            'heading' => 'Submit a membership application',
            'description' => 'Enter the applicant’s details. The assigned Field Officer will review the request before an official member record is created.',
        ])

        <form method="POST"
              action="{{ route('membership.applications.store') }}"
              class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf

            @include('shared.membership.partials.profile-fields')

            <p class="rounded-lg bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                Submitting saves a Pending application. A separate Field Officer
                decision is required for approval or rejection.
            </p>

            <button type="submit" class="am-user-button am-user-button-primary">
                Submit application
            </button>
        </form>
    </div>
</x-dashboard-layout>