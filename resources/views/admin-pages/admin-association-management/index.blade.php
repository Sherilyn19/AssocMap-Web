{{--
    resources/views/admin-pages/admin-association-management/index.blade.php

    Association Management Module - System Administrator view.

    Notes:
    - Uses the same x-dashboard-layout component as the working Admin modules.
    - Association records are archived/restored instead of permanently deleted.
    - Member counts are calculated by the service and are not stored manually.
    - The edit payload is prepared in a PHP block to avoid multiline @json
      parsing errors inside an HTML attribute.
--}}

<x-dashboard-layout title="Association Management">
<div
    class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8"
    data-association-page
    data-barangays="{{ json_encode($barangays) }}"
>
    {{-- Page heading --}}
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    BFAR SAAD Phase II
                </span>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Association Management
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Manage association records, personnel assignments, and operational status.
                </p>
            </div>

            <button
                type="button"
                data-open-modal="create-association-modal"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5
                       text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700
                       focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Association
            </button>
        </div>
    </header>

    {{-- Flash and validation feedback --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
             role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
             role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
             role="alert">
            <p class="font-semibold">Please correct the highlighted information.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Summary cards --}}
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Association summary">
        @php
            $cards = [
                ['label' => 'Total Associations', 'value' => $summary['total'], 'hint' => 'All records'],
                ['label' => 'Active Associations', 'value' => $summary['active'], 'hint' => 'Current and operational'],
                ['label' => 'Inactive Associations', 'value' => $summary['inactive'], 'hint' => 'Current but inactive'],
                ['label' => 'Archived Associations', 'value' => $summary['archived'], 'hint' => 'Retained historical records'],
            ];
        @endphp

        @foreach ($cards as $card)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    {{-- Filters --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.associations.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <div class="relative mt-1.5">
                        <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-4.35-4.35m1.1-5.4a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Association name or address"
                            class="min-h-11 w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-3
                                   text-sm text-slate-900 placeholder:text-slate-400
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Municipality</span>
                    <select name="area_unit_id" data-filter-municipality
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2
                                   text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All municipalities</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}"
                                @selected((string) ($filters['area_unit_id'] ?? '') === (string) $municipality->id)>
                                {{ $municipality->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Barangay</span>
                    <select name="sub_unit_id" data-filter-barangay
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2
                                   text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All barangays</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Program component</span>
                    <select name="program_component_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All components</option>
                        @foreach ($programComponents as $component)
                            <option value="{{ $component->id }}"
                                @selected((string) ($filters['program_component_id'] ?? '') === (string) $component->id)>
                                {{ $component->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Field Officer</span>
                    <select name="field_officer_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All Field Officers</option>
                        @foreach ($fieldOfficers as $officer)
                            <option value="{{ $officer->id }}"
                                @selected((string) ($filters['field_officer_id'] ?? '') === (string) $officer->id)>
                                {{ $officer->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Operational status</span>
                    <select name="status_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Active and inactive</option>
                        @foreach ($associationStatuses as $status)
                            <option value="{{ $status->id }}"
                                @selected((string) ($filters['status_id'] ?? '') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Record state</span>
                    <select name="archive_state"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="current" @selected(($filters['archive_state'] ?? 'current') === 'current')>Current</option>
                        <option value="archived" @selected(($filters['archive_state'] ?? '') === 'archived')>Archived</option>
                        <option value="all" @selected(($filters['archive_state'] ?? '') === 'all')>All records</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select name="sort"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>Name A–Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z–A</option>
                        <option value="date_joined_desc" @selected(($filters['sort'] ?? '') === 'date_joined_desc')>Newest joined</option>
                        <option value="date_joined_asc" @selected(($filters['sort'] ?? '') === 'date_joined_asc')>Oldest joined</option>
                        <option value="created_desc" @selected(($filters['sort'] ?? '') === 'created_desc')>Recently created</option>
                        <option value="updated_desc" @selected(($filters['sort'] ?? '') === 'updated_desc')>Recently updated</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('admin.associations.index') }}"
                   class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 py-2
                          text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset filters
                </a>
                <button type="submit"
                        class="inline-flex min-h-10 items-center rounded-lg bg-slate-800 px-4 py-2
                               text-sm font-semibold text-white hover:bg-slate-700">
                    Apply filters
                </button>
            </div>
        </form>
    </section>

    {{-- Desktop table --}}
    <section class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Association', 'Location', 'Program', 'Assigned Personnel', 'Members', 'Status'] as $heading)
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ $heading }}
                            </th>
                        @endforeach
                        <th scope="col"
                            class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 px-4 py-3
                                   text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($associations as $association)
                        <tr class="group align-top hover:bg-slate-50/70">
                            <td class="px-4 py-4">
                                <p class="max-w-xs font-semibold text-slate-900">{{ $association->name }}</p>
                                <p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $association->address }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                <p>{{ $association->areaUnit?->name ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $association->subUnit?->name ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                {{ $association->programComponent?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                <p class="font-medium text-slate-800">{{ $association->fieldOfficer?->name ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $association->fieldOfficer?->email }}</p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Representative:
                                    <span class="font-medium text-slate-700">
                                        @if ($association->representative)
                                            {{ $association->representative->first_name }}
                                            {{ $association->representative->last_name }}
                                        @else
                                            Not assigned
                                        @endif
                                    </span>
                                </p>
                            </td>
                            <td class="px-4 py-4 text-sm font-semibold tabular-nums text-slate-900">
                                {{ $association->members_count }}
                            </td>
                            <td class="px-4 py-4">
                                @php $isActive = $association->status?->status_name === 'Active'; @endphp
                                <div class="flex flex-col items-start gap-2">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $association->status?->status_name ?? 'Unknown' }}
                                    </span>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $association->is_archived ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $association->is_archived ? 'Archived' : 'Current' }}
                                    </span>
                                </div>
                            </td>
                            <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-4 group-hover:bg-slate-50">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.associations.show', ['association' => $association, ...$listState]) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold
                                              text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>

                                    @unless ($association->is_archived)
                                        {{--
                                            Build the edit payload before the button.
                                            Keeping the array outside the HTML attribute prevents
                                            Blade from misreading the closing brackets.
                                        --}}
                                        @php
                                            $editAssociationData = [
                                                'id' => $association->id,
                                                'name' => $association->name,
                                                'area_unit_id' => $association->area_unit_id,
                                                'sub_unit_id' => $association->sub_unit_id,
                                                'program_component_id' => $association->program_component_id,
                                                'field_officer_id' => $association->field_officer_id,
                                                'status_id' => $association->status_id,
                                                'address' => $association->address,
                                                'date_joined' => $association->date_joined?->format('Y-m-d'),
                                                'representative_member_id' => $association->representative_member_id,
                                                'update_url' => route('admin.associations.update', $association),
                                            ];
                                        @endphp

                                        <button type="button"
                                                data-edit-association="{{ json_encode($editAssociationData) }}"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold
                                                       text-slate-700 hover:bg-slate-50">
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('admin.associations.archive', $association) }}"
                                              data-confirm-form
                                              data-confirm-message="Archive this association? Existing records will remain and published GIS locations will be unpublished.">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold
                                                           text-red-700 hover:bg-red-50">
                                                Archive
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.associations.restore', $association) }}"
                                              data-confirm-form
                                              data-confirm-message="Restore this association? GIS locations will remain unpublished.">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold
                                                           text-emerald-700 hover:bg-emerald-50">
                                                Restore
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                    <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M18 18.72a9.094 9.094 0 0 0 3.742-.479 3 3 0 0 0-4.682-2.72M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </div>
                                <h2 class="mt-4 font-semibold text-slate-900">No associations found</h2>
                                <p class="mt-1 text-sm text-slate-500">Adjust the filters or create the first association record.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Mobile cards --}}
    <section class="grid gap-4 sm:grid-cols-2 lg:hidden">
        @forelse ($associations as $association)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ $association->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $association->subUnit?->name }}, {{ $association->areaUnit?->name }}
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ $association->is_archived ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-800' }}">
                        {{ $association->is_archived ? 'Archived' : 'Current' }}
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Program</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $association->programComponent?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Members</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $association->members_count }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-slate-500">Field Officer</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $association->fieldOfficer?->name ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <a href="{{ route('admin.associations.show', ['association' => $association, ...$listState]) }}"
                       class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2
                              text-sm font-semibold text-slate-700">
                        View details
                    </a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <p class="font-semibold text-slate-900">No associations found</p>
                <p class="mt-1 text-sm text-slate-500">Adjust the filters or create a record.</p>
            </div>
        @endforelse
    </section>

    <div>
        {{ $associations->links() }}
    </div>

    {{-- Create modal --}}
    <div id="create-association-modal" data-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="create-association-title">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h2 id="create-association-title" class="text-lg font-bold text-slate-900">Add Association</h2>
                        <p class="mt-1 text-sm text-slate-500">Create the official BFAR SAAD association master record.</p>
                    </div>
                    <button type="button" data-close-modal
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">
                        <span aria-hidden="true" class="text-xl">&times;</span>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.associations.store') }}" class="space-y-6 p-6">
                    @csrf
                    @include('admin-pages.admin-association-management.partials.form-fields', [
                        'prefix' => 'create',
                        'association' => null,
                    ])
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" data-close-modal
                                class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit"
                                class="min-h-11 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Create Association
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="edit-association-modal" data-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="edit-association-title">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h2 id="edit-association-title" class="text-lg font-bold text-slate-900">Edit Association</h2>
                        <p class="mt-1 text-sm text-slate-500">Update the association master information.</p>
                    </div>
                    <button type="button" data-close-modal
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">
                        <span aria-hidden="true" class="text-xl">&times;</span>
                    </button>
                </div>

                <form method="POST" action="" data-edit-form class="space-y-6 p-6">
                    @csrf
                    @method('PUT')
                    @include('admin-pages.admin-association-management.partials.form-fields', [
                        'prefix' => 'edit',
                        'association' => null,
                    ])
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" data-close-modal
                                class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit"
                                class="min-h-11 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</x-dashboard-layout>