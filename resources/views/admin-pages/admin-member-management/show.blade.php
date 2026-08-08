{{--
    resources/views/admin-pages/admin-member-management/show.blade.php
    Full authorized administrative record for one official member.
--}}
<x-dashboard-layout :title="$member->first_name.' '.$member->last_name">
@php
    $fullName = trim(implode(' ', array_filter([
        $member->first_name,
        $member->middle_name,
        $member->last_name,
    ], fn ($part) => filled($part))));

    $reviewerName = $member->application?->reviewer
        ? trim(implode(' ', array_filter([
            $member->application->reviewer->first_name,
            $member->application->reviewer->middle_name,
            $member->application->reviewer->last_name,
        ], fn ($part) => filled($part))))
        : null;

    $isRepresentative = (int) ($member->association?->representative_member_id ?? 0) === (int) $member->id;
@endphp

<div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <a href="{{ $backToListUrl }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
            &larr; Back to Member Management
        </a>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    Official Member Record
                </span>
                <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $fullName }}
                </h1>
                <p class="mt-2 break-words text-sm text-slate-600">
                    {{ $member->association?->name ?? 'Unknown association' }}
                    &middot; {{ $member->association?->subUnit?->name ?? 'No barangay' }},
                    {{ $member->association?->areaUnit?->name ?? 'No municipality' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $member->is_archived ? 'Archived' : 'Current' }}
                </span>
                @if ($isRepresentative)
                    <span class="inline-flex rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700">
                        Association Representative
                    </span>
                @endif
            </div>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Identity & Contact</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Full name', $fullName],
                    ['Birthday', $member->birthday?->format('F j, Y')],
                    ['Sex', $member->sex?->sex_name],
                    ['Contact number', $member->contact_number],
                    ['Address', $member->address, true],
                ] as $item)
                    <div class="{{ ($item[2] ?? false) ? 'sm:col-span-2' : '' }}">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Membership</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Association', $member->association?->name],
                    ['Municipality', $member->association?->areaUnit?->name],
                    ['Barangay', $member->association?->subUnit?->name],
                    ['Association role', $member->role_in_assoc],
                    ['Beneficiary type', $member->beneficiary_type],
                    ['Date registered', $member->date_registered?->format('F j, Y')],
                ] as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Source Application</h2>
            @if ($member->application)
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Application ID', '#'.$member->application->id],
                        ['Status', $member->application->status?->status_name],
                        ['Submitted', $member->application->created_at?->format('F j, Y g:i A')],
                        ['Reviewed by', $reviewerName],
                        ['Reviewed at', $member->application->reviewed_at?->format('F j, Y g:i A')],
                    ] as $item)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                            <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '-' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <a
                    href="{{ route('members.applications.show', ['application' => $member->application]) }}"
                    class="mt-5 inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Open Application Record
                </a>
            @else
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    This record has no linked member application. It may be a legacy or manually migrated historical record.
                </p>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">System Information</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Member record ID', '#'.$member->id],
                    ['Linked user account', $member->user?->email],
                    ['Created', $member->created_at?->format('F j, Y g:i A')],
                    ['Last updated', $member->updated_at?->format('F j, Y g:i A')],
                ] as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</div>
</x-dashboard-layout>