{{--
    resources/views/admin-pages/admin-member-management/applications.blade.php

    Administrator monitoring view only.
    Approval and rejection remain Association Representative responsibilities.
--}}
<x-dashboard-layout title="Member Applications">
<div class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    BFAR SAAD Phase II
                </span>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Member Applications
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Monitor Pending, Approved, and Rejected membership requests without bypassing Association Representative review.
                </p>
            </div>

            <a
                href="{{ route('members.index') }}"
                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5
                       text-sm font-semibold text-slate-700 transition hover:bg-slate-50
                       focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
            >
                Back to Official Members
            </a>
        </div>

        <nav class="mt-5 flex gap-2 border-t border-slate-200 pt-4" aria-label="Member Management sections">
            <a
                href="{{ route('members.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            >
                Official Members
            </a>
            <a
                href="{{ route('members.applications.index') }}"
                aria-current="page"
                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white"
            >
                Applications
            </a>
        </nav>
    </header>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">
        <strong>Administrative monitoring only.</strong>
        Only the designated Association Representative may approve or reject member applications.
    </div>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Application summary">
        @foreach ([
            ['Total Applications', $summary['total'], 'All retained requests'],
            ['Pending', $summary['pending'], 'Awaiting representative review'],
            ['Approved', $summary['approved'], 'Converted through the approved workflow'],
            ['Rejected', $summary['rejected'], 'Retained for audit history'],
        ] as [$label, $value, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('members.applications.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Applicant or association name"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Association</span>
                    <select name="association_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}" @selected((string) ($filters['association_id'] ?? '') === (string) $association->id)>
                                {{ $association->name }}{{ $association->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Status</span>
                    <select name="status_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All statuses</option>
                        @foreach ($applicationStatuses as $status)
                            <option value="{{ $status->id }}" @selected((string) ($filters['status_id'] ?? '') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Submitted From</span>
                    <input type="date" name="submitted_from" value="{{ $filters['submitted_from'] ?? '' }}"
                           class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                                  focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Submitted To</span>
                    <input type="date" name="submitted_to" value="{{ $filters['submitted_to'] ?? '' }}"
                           class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                                  focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select name="sort"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="submitted_desc" @selected(($filters['sort'] ?? 'submitted_desc') === 'submitted_desc')>Newest Submitted</option>
                        <option value="submitted_asc" @selected(($filters['sort'] ?? '') === 'submitted_asc')>Oldest Submitted</option>
                        <option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Applicant A–Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Applicant Z–A</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Rows per page</span>
                    <select name="per_page"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) ($filters['per_page'] ?? 15) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4">
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    Apply Filters
                </button>
                <a href="{{ route('members.applications.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset Filters
                </a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Application Records</h2>
            <p class="mt-1 text-xs text-slate-500">
                Showing {{ $applications->firstItem() ?? 0 }}-{{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} applications
            </p>
        </div>

        @if ($applications->isEmpty())
            <div class="px-5 py-14 text-center">
                <h3 class="text-base font-semibold text-slate-900">No applications found</h3>
                <p class="mt-2 text-sm text-slate-500">No membership applications match the selected filters.</p>
                <a href="{{ route('members.applications.index') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset Filters
                </a>
            </div>
        @else
            <div class="hidden lg:block">
                <table class="w-full table-fixed border-collapse">
                    <caption class="sr-only">Member applications</caption>
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="w-[23%] px-4 py-3">Applicant</th>
                            <th class="w-[23%] px-4 py-3">Association</th>
                            <th class="w-[12%] px-4 py-3">Status</th>
                            <th class="w-[18%] px-4 py-3">Reviewer / Representative</th>
                            <th class="w-[14%] px-4 py-3">Timeline</th>
                            <th class="w-[10%] px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($applications as $application)
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
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-semibold text-slate-900">{{ $applicantName }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $application->sex?->sex_name ?? 'Sex not recorded' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $application->association?->name ?? 'Unknown association' }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $application->association?->subUnit?->name ?? 'No barangay' }}, {{ $application->association?->areaUnit?->name ?? 'No municipality' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statusName }}
                                    </span>
                                    @if ($application->member)
                                        <p class="mt-1 text-[11px] text-slate-500">Member #{{ $application->member->id }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if ($reviewerName)
                                        <p class="break-words text-sm text-slate-800">{{ $reviewerName }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Reviewed application</p>
                                    @else
                                        <p class="break-words text-sm text-slate-800">{{ $representativeName ?: 'No representative assigned' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Current representative</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    <p>Submitted {{ $application->created_at?->format('M j, Y') }}</p>
                                    @if ($application->reviewed_at)
                                        <p class="mt-1">Reviewed {{ $application->reviewed_at->format('M j, Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('members.applications.show', ['application' => $application, ...request()->query()]) }}"
                                       class="inline-flex rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden">
                @foreach ($applications as $application)
                    @php
                        $applicantName = trim(implode(' ', array_filter([
                            $application->first_name,
                            $application->middle_name,
                            $application->last_name,
                        ], fn ($part) => filled($part))));
                        $statusName = $application->status?->status_name ?? 'Unknown';
                        $statusClass = match ($statusName) {
                            'Approved' => 'bg-emerald-50 text-emerald-700',
                            'Rejected' => 'bg-red-50 text-red-700',
                            default => 'bg-amber-50 text-amber-800',
                        };
                    @endphp
                    <article class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-bold text-slate-900">{{ $applicantName }}</p>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $application->association?->name ?? 'Unknown association' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusName }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">Submitted {{ $application->created_at?->format('M j, Y') }}</p>
                            <a href="{{ route('members.applications.show', ['application' => $application, ...request()->query()]) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                View
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-4 py-4 sm:px-5">
                {{ $applications->links() }}
            </div>
        @endif
    </section>
</div>
</x-dashboard-layout>