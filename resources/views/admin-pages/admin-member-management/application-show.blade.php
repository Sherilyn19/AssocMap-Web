{{--
    resources/views/admin-pages/admin-member-management/application-show.blade.php
    Read-only System Administrator inspection of one membership application.
--}}
<x-dashboard-layout title="Member Application">
@php
    $applicantName = trim(implode(' ', array_filter([
        $application->first_name,
        $application->middle_name,
        $application->last_name,
    ], fn ($part) => filled($part))));

    $reviewerName = $application->reviewer
        ? trim(implode(' ', array_filter([
            $application->reviewer->first_name,
            $application->reviewer->middle_name,
            $application->reviewer->last_name,
        ], fn ($part) => filled($part))))
        : null;

    $representativeName = $application->association?->representative
        ? trim(implode(' ', array_filter([
            $application->association->representative->first_name,
            $application->association->representative->middle_name,
            $application->association->representative->last_name,
        ], fn ($part) => filled($part))))
        : null;

    $statusName = $application->status?->status_name ?? 'Unknown';
    $statusClass = match ($statusName) {
        'Approved' => 'bg-emerald-50 text-emerald-700',
        'Rejected' => 'bg-red-50 text-red-700',
        default => 'bg-amber-50 text-amber-800',
    };
@endphp

<div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <a href="{{ $backToListUrl }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
            ← Back to Member Applications
        </a>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    Application #{{ $application->id }}
                </span>
                <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $applicantName }}
                </h1>
                <p class="mt-2 break-words text-sm text-slate-600">
                    {{ $application->association?->name ?? 'Unknown association' }}
                </p>
            </div>

            <span class="inline-flex self-start rounded-full px-3 py-1.5 text-xs font-semibold {{ $statusClass }}">
                {{ $statusName }}
            </span>
        </div>
    </header>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">
        This Admin view is read-only. Approval and rejection remain the responsibility of the designated Association Representative.
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Applicant Information</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Full name', $applicantName],
                    ['Birthday', $application->birthday?->format('F j, Y')],
                    ['Sex', $application->sex?->sex_name],
                    ['Beneficiary type', $application->beneficiary_type],
                    ['Contact number', $application->contact_number],
                    ['Address', $application->address, true],
                ] as $item)
                    <div class="{{ ($item[2] ?? false) ? 'sm:col-span-2' : '' }}">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Association & Workflow</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Association', $application->association?->name],
                    ['Municipality', $application->association?->areaUnit?->name],
                    ['Barangay', $application->association?->subUnit?->name],
                    ['Current representative', $representativeName],
                    ['Application status', $statusName],
                    ['Submitted', $application->created_at?->format('F j, Y g:i A')],
                    ['Reviewed by', $reviewerName],
                    ['Reviewed at', $application->reviewed_at?->format('F j, Y g:i A')],
                ] as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($statusName === 'Rejected')
            <section class="rounded-xl border border-red-200 bg-red-50 p-5 lg:col-span-2">
                <h2 class="text-base font-bold text-red-900">Rejection Reason</h2>
                <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-red-800">
                    {{ $application->rejection_reason ?: 'No rejection reason is recorded.' }}
                </p>
            </section>
        @endif

        @if ($application->member)
            <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 lg:col-span-2">
                <h2 class="text-base font-bold text-emerald-900">Resulting Official Member</h2>
                <p class="mt-2 break-words text-sm text-emerald-800">
                    This approved application is linked to Member #{{ $application->member->id }}:
                    {{ trim($application->member->first_name.' '.($application->member->middle_name ?? '').' '.$application->member->last_name) }}.
                </p>
                <a href="{{ route('members.show', $application->member) }}"
                   class="mt-4 inline-flex rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Open Member Record
                </a>
            </section>
        @endif
    </div>
</div>
</x-dashboard-layout>