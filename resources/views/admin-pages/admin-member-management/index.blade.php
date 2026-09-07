{{--
    resources/views/admin-pages/admin-member-management/index.blade.php

    System Administrator - Member Management
    Official members only. Applications remain a separate read-only Admin workflow view.
--}}
<x-dashboard-layout title="Member Management" topbar-title="Member Management">
<div
    class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8"
    data-management-register
    data-member-management-page
    data-barangays="{{ json_encode($barangays->map(fn ($barangay) => [
        'id' => $barangay->id,
        'area_unit_id' => $barangay->area_unit_id,
        'name' => $barangay->name,
        'is_archived' => (bool) $barangay->is_archived,
    ])->values()) }}"
>
    {{-- Header --}}
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    BFAR SAAD Phase II
                </span>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Member Management
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Manage approved association members, membership records, and historical beneficiary information.
                </p>
            </div>

            <a
                href="{{ route('members.applications.index') }}"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300
                       bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50
                       focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
            >
                View Applications
            </a>
        </div>

        <nav class="mt-5 flex gap-2 border-t border-slate-200 pt-4" aria-label="Member Management sections">
            <a
                href="{{ route('members.index') }}"
                aria-current="page"
                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white"
            >
                Official Members
            </a>
            <a
                href="{{ route('members.applications.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            >
                Applications
            </a>
        </nav>
    </header>

    {{-- Feedback --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Please correct the highlighted information.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Clickable analytics --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Member summary">
        @php
            $summaryCards = [
                [
                    'modal' => 'total-members-modal',
                    'label' => 'Total Official Members',
                    'value' => $summary['total'],
                    'hint' => 'All retained member records',
                ],
                [
                    'modal' => 'current-members-modal',
                    'label' => 'Current Members',
                    'value' => $summary['current'],
                    'hint' => 'Not archived',
                ],
                [
                    'modal' => 'archived-members-modal',
                    'label' => 'Archived Members',
                    'value' => $summary['archived'],
                    'hint' => 'Historical records',
                ],
                [
                    'modal' => 'representatives-modal',
                    'label' => 'Association Representatives',
                    'value' => $summary['representatives'],
                    'hint' => 'Current designated representatives',
                ],
                [
                    'modal' => 'associations-with-members-modal',
                    'label' => 'Associations With Members',
                    'value' => $summary['associations_with_members'],
                    'hint' => 'Associations represented in records',
                ],
            ];
        @endphp

        @foreach ($summaryCards as $card)
            <button
                type="button"
                data-open-modal="{{ $card['modal'] }}"
                data-analytics-card
                class="rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition
                       hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md
                       focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                aria-label="View details for {{ $card['label'] }}"
            >
                <p class="text-sm font-medium text-slate-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $card['hint'] }}</p>
            </button>
        @endforeach
    </section>

    {{-- Filters --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('members.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Member or association name"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none
                               focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Association</span>
                    <select
                        name="association_id"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}" @selected((string) ($filters['association_id'] ?? '') === (string) $association->id)>
                                {{ $association->name }}{{ $association->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Record State</span>
                    <select
                        name="record_state"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="current" @selected(($filters['record_state'] ?? 'current') === 'current')>Current</option>
                        <option value="archived" @selected(($filters['record_state'] ?? '') === 'archived')>Archived</option>
                        <option value="all" @selected(($filters['record_state'] ?? '') === 'all')>All</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Municipality</span>
                    <select
                        name="area_unit_id"
                        data-filter-municipality
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All municipalities</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}" @selected((string) ($filters['area_unit_id'] ?? '') === (string) $municipality->id)>
                                {{ $municipality->name }}{{ $municipality->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Barangay</span>
                    <select
                        name="sub_unit_id"
                        data-filter-barangay
                        data-all-label="All barangays"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               disabled:bg-slate-100 disabled:text-slate-400
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">Select municipality first</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sex</span>
                    <select
                        name="sex_id"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All</option>
                        @foreach ($sexOptions as $sex)
                            <option value="{{ $sex->id }}" @selected((string) ($filters['sex_id'] ?? '') === (string) $sex->id)>
                                {{ $sex->sex_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Association Role</span>
                    <select
                        name="role_in_assoc"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $role)
                            <option value="{{ $role }}" @selected(($filters['role_in_assoc'] ?? '') === $role)>
                                {{ $role }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Beneficiary Type</span>
                    <select
                        name="beneficiary_type"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All beneficiary types</option>
                        @foreach ($beneficiaryTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['beneficiary_type'] ?? '') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Registered From</span>
                    <input
                        type="date"
                        name="registered_from"
                        value="{{ $filters['registered_from'] ?? '' }}"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Registered To</span>
                    <input
                        type="date"
                        name="registered_to"
                        value="{{ $filters['registered_to'] ?? '' }}"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select
                        name="sort"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>Name A-Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z-A</option>
                        <option value="registered_desc" @selected(($filters['sort'] ?? '') === 'registered_desc')>Newest Registered</option>
                        <option value="registered_asc" @selected(($filters['sort'] ?? '') === 'registered_asc')>Oldest Registered</option>
                        <option value="association_asc" @selected(($filters['sort'] ?? '') === 'association_asc')>Association A-Z</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Rows per page</span>
                    <select
                        name="per_page"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) ($filters['per_page'] ?? 15) === $option)>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4">
                <button
                    type="submit"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-800 px-4 py-2
                           text-sm font-semibold text-white transition hover:bg-slate-700
                           focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                >
                    Apply Filters
                </button>
                <a
                    href="{{ route('members.index') }}"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300
                           bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50
                           focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                >
                    Reset Filters
                </a>
            </div>
        </form>
    </section>

    @php
        /*
         * Build only current-page presentation payloads for quick View/Edit modals.
         * This avoids loading the complete member dataset into JavaScript.
         */
        $memberPresentation = static function ($member) use ($listState): array {
            $fullName = trim(implode(' ', array_filter([
                $member->first_name,
                $member->middle_name,
                $member->last_name,
            ], fn ($part) => filled($part))));

            $locationLabel = trim(
                ($member->association?->subUnit?->name ?? 'No barangay')
                .', '
                .($member->association?->areaUnit?->name ?? 'No municipality')
            );

            $isRepresentative = (int) ($member->association?->representative_member_id ?? 0) === (int) $member->id;

            $reviewerName = $member->application?->reviewer
                ? trim(implode(' ', array_filter([
                    $member->application->reviewer->first_name,
                    $member->application->reviewer->middle_name,
                    $member->application->reviewer->last_name,
                ], fn ($part) => filled($part))))
                : null;

            $detailPayload = [
                'id' => $member->id,
                'full_name' => $fullName,
                'birthday' => $member->birthday?->format('F j, Y'),
                'sex' => $member->sex?->sex_name,
                'contact_number' => $member->contact_number,
                'address' => $member->address,
                'association' => $member->association?->name,
                'municipality' => $member->association?->areaUnit?->name,
                'barangay' => $member->association?->subUnit?->name,
                'role_in_assoc' => $member->role_in_assoc,
                'beneficiary_type' => $member->beneficiary_type,
                'date_registered' => $member->date_registered?->format('F j, Y'),
                'record_state' => $member->is_archived ? 'Archived' : 'Current',
                'representative_state' => $isRepresentative ? 'Current Association Representative' : 'No',
                'application_id' => $member->application_id,
                'application_status' => $member->application?->status?->status_name,
                'application_submitted_at' => $member->application?->created_at?->format('F j, Y g:i A'),
                'reviewed_by' => $reviewerName,
                'reviewed_at' => $member->application?->reviewed_at?->format('F j, Y g:i A'),
                'created_at' => $member->created_at?->format('F j, Y g:i A'),
                'updated_at' => $member->updated_at?->format('F j, Y g:i A'),
                'show_url' => route('members.show', [
                    'member' => $member,
                    ...$listState,
                ]),
            ];

            $editPayload = [
                'full_name' => $fullName,
                'first_name' => $member->first_name,
                'middle_name' => $member->middle_name,
                'last_name' => $member->last_name,
                'birthday' => $member->birthday?->format('Y-m-d'),
                'sex_id' => $member->sex_id,
                'role_in_assoc' => $member->role_in_assoc,
                'beneficiary_type' => $member->beneficiary_type,
                'contact_number' => $member->contact_number,
                'address' => $member->address,
                'date_registered' => $member->date_registered?->format('Y-m-d'),
                'update_url' => route('members.update', $member),
            ];

            $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

            return [
                'full_name' => $fullName,
                'location_label' => $locationLabel,
                'is_representative' => $isRepresentative,
                'detail_payload_json' => json_encode($detailPayload, $jsonFlags),
                'edit_payload_json' => json_encode($editPayload, $jsonFlags),
            ];
        };
    @endphp

    {{-- Records --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="member-records-title">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <h2 id="member-records-title" class="font-semibold text-slate-900">Official Member Records</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Showing {{ $members->firstItem() ?? 0 }}-{{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members
                </p>
            </div>
            <p class="text-xs text-slate-500">Private administrative information</p>
        </div>

        @if ($members->isEmpty())
            <div class="px-5 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8c0-3.5 3.1-6 7-6s7 2.5 7 6" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">No members found</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                    No official member records match the selected filters.
                </p>
                <a href="{{ route('members.index') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset Filters
                </a>
            </div>
        @else
            {{-- Desktop: 7 intentional columns; no horizontal-scroll container. --}}
            <div class="hidden xl:block">
                <table class="w-full table-fixed border-collapse">
                    <caption class="sr-only">Official association members</caption>
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="w-[18%] px-4 py-3">Member</th>
                            <th scope="col" class="w-[21%] px-4 py-3">Association</th>
                            <th scope="col" class="w-[16%] px-4 py-3">Role / Beneficiary</th>
                            <th scope="col" class="w-[13%] px-4 py-3">Contact</th>
                            <th scope="col" class="w-[11%] px-4 py-3">Registered</th>
                            <th scope="col" class="w-[9%] px-4 py-3">State</th>
                            <th scope="col" class="w-[12%] px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($members as $member)
                            @php
                                $presentation = $memberPresentation($member);
                                $memberFullName = $presentation['full_name'];
                                $locationLabel = $presentation['location_label'];
                                $isRepresentative = $presentation['is_representative'];
                                $detailPayloadJson = $presentation['detail_payload_json'];
                                $editPayloadJson = $presentation['edit_payload_json'];
                            @endphp
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-4">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white">
                                            {{ strtoupper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="break-words text-sm font-semibold text-slate-900">{{ $memberFullName }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $member->sex?->sex_name ?? 'Sex not recorded' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $member->association?->name ?? 'Unknown association' }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $locationLabel }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $member->role_in_assoc ?: 'Unassigned' }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $member->beneficiary_type ?: 'Beneficiary type not set' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ $member->contact_number ?: '-' }}</td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ $member->date_registered?->format('M j, Y') ?? '-”' }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $member->is_archived ? 'Archived' : 'Current' }}
                                        </span>
                                        @if ($isRepresentative)
                                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                                Representative
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @include('admin-pages.admin-member-management.partials.member-actions', [
                                        'member' => $member,
                                        'memberFullName' => $memberFullName,
                                        'detailPayloadJson' => $detailPayloadJson,
                                        'editPayloadJson' => $editPayloadJson,
                                        'isRepresentative' => $isRepresentative,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tablet/smaller laptop: five meaningful information groups. --}}
            <div class="hidden md:block xl:hidden">
                <div class="grid grid-cols-[1.25fr_1.25fr_0.9fr_0.7fr_auto] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <div>Member</div>
                    <div>Association</div>
                    <div>Role</div>
                    <div>State</div>
                    <div class="text-right">Actions</div>
                </div>

                @foreach ($members as $member)
                    @php
                        $presentation = $memberPresentation($member);
                        $memberFullName = $presentation['full_name'];
                        $locationLabel = $presentation['location_label'];
                        $isRepresentative = $presentation['is_representative'];
                        $detailPayloadJson = $presentation['detail_payload_json'];
                        $editPayloadJson = $presentation['edit_payload_json'];
                    @endphp
                    <div class="grid grid-cols-[1.25fr_1.25fr_0.9fr_0.7fr_auto] gap-3 border-b border-slate-100 px-4 py-4 last:border-b-0">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-slate-900">{{ $memberFullName }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $member->sex?->sex_name ?? '-' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="break-words text-sm font-medium text-slate-800">{{ $member->association?->name ?? 'Unknown' }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $locationLabel }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="break-words text-sm text-slate-800">{{ $member->role_in_assoc ?: 'Unassigned' }}</p>
                        </div>
                        <div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $member->is_archived ? 'Archived' : 'Current' }}
                            </span>
                            @if ($isRepresentative)
                                <p class="mt-1 text-[11px] font-medium text-blue-700">Representative</p>
                            @endif
                        </div>
                        <div>
                            @include('admin-pages.admin-member-management.partials.member-actions', [
                                'member' => $member,
                                'memberFullName' => $memberFullName,
                                'detailPayloadJson' => $detailPayloadJson,
                                'editPayloadJson' => $editPayloadJson,
                                'isRepresentative' => $isRepresentative,
                            ])
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Mobile cards --}}
            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($members as $member)
                    @php
                        $presentation = $memberPresentation($member);
                        $memberFullName = $presentation['full_name'];
                        $locationLabel = $presentation['location_label'];
                        $isRepresentative = $presentation['is_representative'];
                        $detailPayloadJson = $presentation['detail_payload_json'];
                        $editPayloadJson = $presentation['edit_payload_json'];
                    @endphp
                    <article class="space-y-4 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-bold text-slate-900">{{ $memberFullName }}</p>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $member->association?->name ?? 'Unknown association' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $member->is_archived ? 'Archived' : 'Current' }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Role</dt>
                                <dd class="mt-1 break-words text-slate-800">{{ $member->role_in_assoc ?: 'Unassigned' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Registered</dt>
                                <dd class="mt-1 text-slate-800">{{ $member->date_registered?->format('M j, Y') ?? '-' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs font-medium text-slate-500">Location</dt>
                                <dd class="mt-1 break-words text-slate-800">{{ $locationLabel }}</dd>
                            </div>
                        </dl>

                        @if ($isRepresentative)
                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                Association Representative
                            </span>
                        @endif

                        @include('admin-pages.admin-member-management.partials.member-actions', [
                            'member' => $member,
                            'memberFullName' => $memberFullName,
                            'detailPayloadJson' => $detailPayloadJson,
                            'editPayloadJson' => $editPayloadJson,
                            'isRepresentative' => $isRepresentative,
                        ])
                    </article>
                @endforeach
            </div>

        @endif
        <x-management-pagination :records="$members" />
    </section>

    {{-- Analytics modals --}}
    <x-admin-modal id="total-members-modal" title="Total Official Members" description="Aggregate information across retained official member records." size="xl">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['total'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Current</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ $summary['current'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-100 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-600">Archived</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['archived'] }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section>
                <h3 class="text-sm font-semibold text-slate-900">Sex Distribution</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['sex_distribution'] as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>{{ $row->sex_name }}</span>
                            <strong>{{ $row->member_count }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No data available.</p>
                    @endforelse
                </div>
            </section>

            <section>
                <h3 class="text-sm font-semibold text-slate-900">Beneficiary Type Distribution</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['beneficiary_distribution'] as $row)
                        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span class="break-words">{{ $row->beneficiary_type }}</span>
                            <strong>{{ $row->member_count }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No data available.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </x-admin-modal>

    <x-admin-modal id="current-members-modal" title="Current Members" description="Current members are official member records where is_archived is false." size="xl">
        <div class="grid gap-6 lg:grid-cols-2">
            <section>
                <h3 class="text-sm font-semibold text-slate-900">Current Members by Association Role</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['role_distribution'] as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>{{ $row->role_name }}</span>
                            <strong>{{ $row->member_count }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No current members available.</p>
                    @endforelse
                </div>
            </section>

            <section>
                <h3 class="text-sm font-semibold text-slate-900">Recent Registrations</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['recent_registrations'] as $row)
                        <div class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="break-words text-sm font-semibold text-slate-900">
                                {{ trim($row->first_name.' '.($row->middle_name ?? '').' '.$row->last_name) }}
                            </p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $row->association_name }} · {{ $row->date_registered }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No recent registrations available.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </x-admin-modal>

    <x-admin-modal id="archived-members-modal" title="Archived Members" description="Archived records remain stored for history, reporting, and audit." size="lg">
        <p class="text-3xl font-bold text-slate-900">{{ $summary['archived'] }}</p>
        <p class="mt-1 text-sm text-slate-500">Archived official member records</p>

        <div class="mt-6 space-y-2">
            @forelse ($analytics['members_by_association']->where('archived_count', '>', 0) as $row)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-3 py-3">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ $row->name }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">{{ $row->barangay_name ?? 'No barangay' }}, {{ $row->municipality_name ?? 'No municipality' }}</p>
                    </div>
                    <span class="shrink-0 text-sm font-bold text-slate-900">{{ $row->archived_count }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">No archived members are currently recorded.</p>
            @endforelse
        </div>
    </x-admin-modal>

    <x-admin-modal id="representatives-modal" title="Association Representatives" description="Current representatives identified through Association Management." size="xl">
        <div class="space-y-3">
            @forelse ($analytics['representatives'] as $row)
                <div class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[1.2fr_1.2fr_1fr]">
                    <div>
                        <p class="break-words text-sm font-semibold text-slate-900">
                            {{ trim($row->first_name.' '.($row->middle_name ?? '').' '.$row->last_name) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">{{ $row->role_in_assoc ?: 'Role not assigned' }}</p>
                    </div>
                    <div>
                        <p class="break-words text-sm text-slate-800">{{ $row->association_name }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">{{ $row->barangay_name ?? 'No barangay' }}, {{ $row->municipality_name ?? 'No municipality' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500">Contact</p>
                        <p class="mt-1 text-sm text-slate-800">{{ $row->contact_number ?: 'Not recorded' }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No Association Representatives are currently assigned.</p>
            @endforelse
        </div>
    </x-admin-modal>

    <x-admin-modal id="associations-with-members-modal" title="Associations With Members" description="Top associations by retained official member records." size="xl">
        <div class="space-y-3">
            @forelse ($analytics['members_by_association'] as $row)
                <div class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[1.5fr_repeat(3,0.6fr)]">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ $row->name }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">{{ $row->barangay_name ?? 'No barangay' }}, {{ $row->municipality_name ?? 'No municipality' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Total</p>
                        <p class="mt-1 font-bold text-slate-900">{{ $row->total_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Current</p>
                        <p class="mt-1 font-bold text-emerald-700">{{ $row->current_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Archived</p>
                        <p class="mt-1 font-bold text-slate-700">{{ $row->archived_count }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No associations have official member records yet.</p>
            @endforelse
        </div>
    </x-admin-modal>

    {{-- Quick details modal --}}
    <x-admin-modal id="member-details-modal" title="Member Details" description="Authorized administrative record details." size="xl">
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ([
                'Identity' => [
                    ['Full name', 'full_name'],
                    ['Birthday', 'birthday'],
                    ['Sex', 'sex'],
                    ['Contact', 'contact_number'],
                    ['Address', 'address'],
                ],
                'Membership' => [
                    ['Association', 'association'],
                    ['Municipality', 'municipality'],
                    ['Barangay', 'barangay'],
                    ['Association role', 'role_in_assoc'],
                    ['Beneficiary type', 'beneficiary_type'],
                    ['Date registered', 'date_registered'],
                    ['Record state', 'record_state'],
                    ['Representative', 'representative_state'],
                ],
                'Source' => [
                    ['Application ID', 'application_id'],
                    ['Application status', 'application_status'],
                    ['Submitted', 'application_submitted_at'],
                    ['Reviewed by', 'reviewed_by'],
                    ['Reviewed at', 'reviewed_at'],
                ],
                'System' => [
                    ['Member record ID', 'id'],
                    ['Created', 'created_at'],
                    ['Last updated', 'updated_at'],
                ],
            ] as $section => $items)
                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-bold text-slate-900">{{ $section }}</h3>
                    <dl class="mt-3 space-y-3">
                        @foreach ($items as [$label, $field])
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                                <dd data-detail-field="{{ $field }}" class="mt-1 break-words text-sm text-slate-800">-</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <button type="button" data-close-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Close
                </button>
                <a data-member-full-record href="#" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                    Open Full Record
                </a>
            </div>
        </x-slot>
    </x-admin-modal>

    {{-- Edit modal --}}
    <x-admin-modal id="edit-member-modal" title="Edit Member" description="Correct official member profile information without changing the source application or association." size="xl">
        <form method="POST" action="#" data-edit-member-form class="space-y-6">
            @csrf
            @method('PUT')

            <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                Editing: <strong data-edit-member-name class="text-slate-900">Member</strong>
            </p>

            <section>
                <h3 class="text-sm font-bold text-slate-900">Personal Information</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['First Name', 'first_name', true],
                        ['Middle Name', 'middle_name', false],
                        ['Last Name', 'last_name', true],
                    ] as [$label, $name, $required])
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                            <input
                                type="text"
                                name="{{ $name }}"
                                data-member-field="{{ $name }}"
                                maxlength="255"
                                @if($required) required @endif
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            >
                        </label>
                    @endforeach

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Birthday</span>
                        <input type="date" name="birthday" data-member-field="birthday" max="{{ now()->format('Y-m-d') }}" required
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Sex</span>
                        <select name="sex_id" data-member-field="sex_id" required
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                            @foreach ($sexOptions as $sex)
                                <option value="{{ $sex->id }}">{{ $sex->sex_name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5">
                <h3 class="text-sm font-bold text-slate-900">Membership Information</h3>
                <p class="mt-1 text-xs text-slate-500">Association ownership and source application are intentionally not editable here.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Association Role</span>
                        <select name="role_in_assoc" data-member-field="role_in_assoc"
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                            <option value="">Unassigned</option>
                            @foreach ($roleOptions as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Beneficiary Type</span>
                        <input type="text" name="beneficiary_type" data-member-field="beneficiary_type" maxlength="100"
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Date Registered</span>
                        <input type="date" name="date_registered" data-member-field="date_registered" max="{{ now()->format('Y-m-d') }}" required
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5">
                <h3 class="text-sm font-bold text-slate-900">Contact Information</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Contact Number</span>
                        <input type="text" name="contact_number" data-member-field="contact_number" maxlength="50"
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Address</span>
                        <textarea name="address" data-member-field="address" rows="3" maxlength="1000"
                                  class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"></textarea>
                    </label>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                <button type="button" data-close-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    Save Changes
                </button>
            </div>
        </form>
    </x-admin-modal>

    {{-- Archive confirmation --}}
    <x-admin-modal id="archive-member-modal" title="Archive Member?" description="This action keeps the record for history, reporting, and audit." size="sm">
        <p class="text-sm leading-6 text-slate-700">
            Archive <strong data-archive-member-name class="text-slate-900">this member</strong>?
            The member will be removed from current member lists but will not be permanently deleted.
        </p>

        <form method="POST" action="#" data-archive-form class="mt-5">
            @csrf
            @method('PATCH')
            <div class="flex justify-end gap-3">
                <button type="button" data-close-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Archive Member
                </button>
            </div>
        </form>
    </x-admin-modal>
</div>
</x-dashboard-layout>