<x-dashboard-layout title="Membership Application" topbar-title="Membership Application">
    <div data-member-workspace class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
        <a class="inline-flex min-h-11 items-center text-blue-800 underline"
           href="{{ route(session('auth_user.role_name') === 'Association Member' ? 'member.applications' : 'membership.index') }}">
            Back to applications
        </a>

        <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="mb-2 break-words text-sm text-slate-500">
                {{ $application->association->name }}
            </p>
            <h1 class="text-2xl font-bold">Application #{{ $application->id }}</h1>
            <div class="mt-3">
                @include('shared.partials.badge', [
                    'label' => $application->status?->status_name,
                ])
            </div>
        </header>

        @include('shared.membership.partials.feedback')

        <section class="rounded-xl border bg-white p-6">
            @include('shared.membership.partials.profile', [
                'record' => $application,
            ])

            @unless ($showPrivateProfile)
                <p class="mt-4 text-sm leading-6 text-slate-600">
                    Personal details are restricted to authorized staff.
                </p>
            @endunless
        </section>

        @if ($application->reviewed_at)
            <section class="space-y-2 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold">Review record</h2>

                <p>
                    Reviewed by
                    {{ $application->reviewer_name ?: 'Not recorded' }}
                    on
                    {{ $application->reviewed_at->copy()->timezone('Asia/Manila')->format('F j, Y g:i A') }}
                    (Asia/Manila).
                </p>

                @if ($application->rejection_reason)
                    <p class="whitespace-pre-wrap break-words">Reason: {{ $application->rejection_reason }}</p>
                @endif

                @if ($application->member)
                    <a class="inline-flex min-h-11 items-center text-blue-800 underline"
                       href="{{ route('membership.members.show', $application->member) }}">
                        View official member record
                    </a>
                @endif
            </section>
        @elseif ($canReview)
            @include('shared.membership.partials.review-form')
        @endif

        @if (!$application->reviewed_at && !$canReview)
            <p class="rounded-xl border bg-slate-50 p-5 text-slate-700">
                This application is pending review by the assigned Field Officer.
            </p>
        @endif
    </div>
</x-dashboard-layout>
