{{--
    resources/views/admin-pages/admin-area-management/admin-area-index.blade.php
    Area Management Module - System Administrator view only.
--}}

<x-dashboard-layout title="Area Management">
<div data-area-management-page data-management-register>
    {{-- Defense: each failed form owns its own error bag and draft; the other form remains clean. --}}
    @php
        $recovery = in_array(old("_area_form"), ["municipality", "barangay"], true) ? [
            "entity" => old("_area_form"), "id" => old("_area_id"),
            "name" => is_string(old("name")) ? old("name") : "",
            "address" => is_string(old("address")) ? old("address") : "",
            "area_unit_id" => is_scalar(old("area_unit_id")) ? old("area_unit_id") : "",
        ] : null;
    @endphp
    <div id="am-form-recovery" data-recovery='@json($recovery)'></div>

    @if (session('success'))
        <div id="am-toast" class="fixed top-5 right-5 z-[60] rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
            {{ session('success') }}
        </div>
    @elseif (session('error'))
        <div role="alert" class="mb-4 rounded-lg bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->getBag('default')->any())
        <div role="alert" class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">{{ $errors->getBag('default')->first() }}</div>
    @endif

    <section class="mb-6 flex flex-col gap-2">
        <h1 class="text-2xl font-bold text-assocmap-text sm:text-3xl">Area Management</h1>
        <p class="max-w-3xl text-sm leading-6 text-assocmap-secondary">
            Manage municipalities and barangays used as the geographic reference layer for association registration,
            GIS filtering, monitoring reports, and coverage validation.
        </p>
    </section>

    @php
        $summaryCards = [
            [
                'key' => 'municipalities',
                'url' => route('areas.index', ['tab' => 'municipalities']),
                'label' => 'Total Municipalities',
                'value' => $summary['total_municipalities'],
                'note' => $summary['active_municipalities'] . ' current · ' . $summary['archived_municipalities'] . ' archived',
                'icon' => 'M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21ZM12 12.3a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6Z',
                'tone' => 'bg-green-50 text-green-700',
            ],
            [
                'key' => 'associations',
                'url' => route('admin.associations.index', ['archive_state' => 'current']),
                'label' => 'Current Associations',
                'value' => $summary['total_associations'],
                'note' => 'Includes operationally Active and Inactive records',
                'icon' => 'M4 21V10l8-6 8 6v11M9 21v-6h6v6',
                'tone' => 'bg-blue-50 text-blue-700',
            ],
            [
                'key' => 'barangays',
                'url' => route('areas.index', ['tab' => 'barangays', 'brgy_status' => 'active']),
                'label' => 'Current Barangays',
                'value' => $summary['active_barangays'],
                'note' => $summary['total_barangays'] . ' total records',
                'icon' => 'M12 11.4a3.4 3.4 0 1 0 0-6.8 3.4 3.4 0 0 0 0 6.8ZM4.5 20c0-4.1 3.4-7 7.5-7s7.5 2.9 7.5 7',
                'tone' => 'bg-teal-50 text-teal-700',
            ],
            [
                'key' => 'coverage',
                'url' => route('areas.index', ['tab' => 'barangays', 'brgy_status' => 'archived']),
                'label' => 'Archived Barangays',
                'value' => $summary['archived_barangays'],
                'note' => 'Historical reference records',
                'icon' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM2 12h20M12 2c3 3 4.5 6.3 4.5 10S15 19 12 22M12 2C9 5 7.5 8.3 7.5 12S9 19 12 22',
                'tone' => 'bg-orange-50 text-orange-700',
            ],
        ];
    @endphp

    <section class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summaryCards as $card)
            <a href="{{ $card['url'] }}" data-am-summary-card="{{ $card['key'] }}"
                    class="rounded-xl border border-assocmap-border bg-white p-5 text-left shadow-card transition hover:-translate-y-0.5 hover:border-assocmap-primary/40 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-assocmap-primary/30">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $card['tone'] }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $card['icon'] }}" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-assocmap-secondary">{{ $card['label'] }}</p>
                        <p class="mt-0.5 text-2xl font-bold text-assocmap-text">{{ $card['value'] }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-assocmap-secondary">{{ $card['note'] }}</p>
            </a>
        @endforeach
    </section>

    <p class="mb-6 text-xs text-assocmap-secondary">Summary totals include all records, independent of the list filters. Select a card to open its matching records.</p>

    <div class="mb-6 inline-flex max-w-full flex-wrap gap-1 rounded-xl border border-assocmap-border bg-white p-1 shadow-card" role="tablist" aria-label="Area registers">
        <button type="button" id="am-tab-municipalities" role="tab" aria-controls="am-panel-municipalities" data-am-tab="municipalities" aria-selected="true"
                class="rounded-lg bg-assocmap-primary px-4 py-2 text-sm font-semibold text-white">
            Municipalities
        </button>
        <button type="button" id="am-tab-barangays" role="tab" aria-controls="am-panel-barangays" data-am-tab="barangays" aria-selected="false"
                class="rounded-lg px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
            Barangays
        </button>
    </div>

    {{-- ================= MUNICIPALITIES PANEL ================= --}}
    <section id="am-panel-municipalities" role="tabpanel" aria-labelledby="am-tab-municipalities" data-am-tab-panel="municipalities">

        <form data-area-filters="municipalities" method="GET" action="{{ route('areas.index') }}" class="mb-6 rounded-xl border border-assocmap-border bg-white p-4 shadow-card">
            {{-- Defense: GET forms omit page numbers, so applying filters or a new
                 shared page size starts on page one. Pagination links retain validated filters. --}}
            <input type="hidden" name="tab" value="municipalities">
            @foreach (['brgy_search', 'brgy_status', 'brgy_sort', 'area_unit_id'] as $key)
                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">
            @endforeach
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label for="area-filter-search" class="text-xs font-medium text-assocmap-secondary">Search</label>
                    <input type="text" id="area-filter-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search municipality or address..."
                           class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                </div>
                <div>
                    <label for="area-filter-status" class="text-xs font-medium text-assocmap-secondary">Status</label>
                    <select id="area-filter-status" name="status" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Current</option>
                        <option value="archived" @selected(($filters['status'] ?? '') === 'archived')>Archived</option>
                    </select>
                </div>
                <div>
                    <label for="area-filter-muni_sort" class="text-xs font-medium text-assocmap-secondary">Sort By</label>
                    <select id="area-filter-muni_sort" name="muni_sort" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                        <option value="name" @selected(($filters['muni_sort'] ?? 'name') === 'name')>Name</option>
                        <option value="created_at" @selected(($filters['muni_sort'] ?? '') === 'created_at')>Newest Created</option>
                        <option value="updated_at" @selected(($filters['muni_sort'] ?? '') === 'updated_at')>Recently Updated</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-assocmap-border pt-4">
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-assocmap-primary px-4 py-2 text-sm font-semibold text-white hover:bg-assocmap-hover">
                        Apply
                    </button>
                    <a href="{{ route('areas.index', array_merge(\Illuminate\Support\Arr::only($filters, ['brgy_search', 'brgy_status', 'brgy_sort', 'area_unit_id', 'brgy_page', 'per_page']), ['tab' => 'municipalities'])) }}" class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                        Reset
                    </a>
                </div>
                <button type="button" data-municipality-modal-open="create"
                        class="rounded-lg bg-assocmap-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-assocmap-hover">
                    + Add Municipality
                </button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-assocmap-secondary">
                <label for="municipalities-per-page">Rows per page</label>
                <select id="municipalities-per-page" name="per_page" class="rounded border border-assocmap-border p-1">@foreach ([12, 24, 48] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 12) == $size)>{{ $size }}</option>@endforeach</select>
                <span>Showing {{ $municipalities->firstItem() ?? 0 }}–{{ $municipalities->lastItem() ?? 0 }} of {{ $municipalities->total() }} matching records</span>
            </div>
</form>

        @include('admin-pages.admin-area-management.filter-chips', ['tab' => 'municipalities'])
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @forelse ($municipalities as $muni)
                @php
                    $muniPayload = [
                        'id' => $muni->id,
                        'name' => $muni->name,
                        'address' => $muni->address,
                        'province' => $muni->province,
                    ];
                @endphp

                {{-- Defense: keep separate native buttons for expansion and actions;
                     a button-like article would hide or confuse its nested controls. --}}
                <article data-area-card="municipality-detail-{{ $muni->id }}"
                         aria-labelledby="municipality-name-{{ $muni->id }}" class="am-area-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 id="municipality-name-{{ $muni->id }}" class="am-area-name text-base font-bold text-[#0A3D7A]">{{ $muni->name }}</h3>
                            <span class="text-xs font-semibold {{ $muni->is_archived ? 'text-gray-600' : 'text-green-700' }}">{{ $muni->is_archived ? 'Archived' : 'Current' }}</span>
                            <p class="mt-3 flex items-start gap-1.5 text-sm text-assocmap-secondary">
                                <svg class="mt-0.5 h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><path d="M12 12.3a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6Z"/>
                                </svg>
                                <span>{{ $muni->address ?: 'No address on file' }}</span>
                            </p>
                        </div>

                        <span class="inline-flex flex-shrink-0 rounded-md bg-green-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-green-700">
                            {{ (int) ($muni->association_count ?? 0) }} Assoc.
                        </span>
                    </div>

                    <p class="mt-3 flex items-start gap-1.5 text-sm text-assocmap-secondary">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 21V10l8-6 8 6v11"/><path d="M9 21v-6h6v6"/>
                        </svg>
                        <span>Barangays: {{ $muni->barangay_count }} current · {{ $muni->total_barangay_count }} total</span>
                    </p>

                    <button type="button" data-area-card-toggle aria-expanded="false"
                            aria-controls="municipality-detail-{{ $muni->id }}"
                            class="mt-3 rounded-md border border-assocmap-border px-3 py-2 text-xs font-semibold">
                        <span data-area-card-toggle-label>Show summary</span><span class="sr-only"> for {{ $muni->name }}</span>
                    </button>
                    <div id="municipality-detail-{{ $muni->id }}" class="am-area-card__details hidden">
                        <dl class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="text-assocmap-secondary">Province</dt>
                                <dd class="mt-1 font-semibold text-assocmap-text">{{ $muni->province ?: 'Cebu' }}</dd>
                            </div>
                            <div>
                                <dt class="text-assocmap-secondary">Status</dt>
                                <dd class="mt-1 font-semibold {{ $muni->is_archived ? 'text-gray-600' : 'text-green-700' }}">
                                    {{ $muni->is_archived ? 'Archived' : 'Current' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-assocmap-secondary">Created</dt>
                                <dd class="mt-1 font-semibold text-assocmap-text">{{ $muni->created_at?->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-assocmap-secondary">Updated</dt>
                                <dd class="mt-1 font-semibold text-assocmap-text">{{ $muni->updated_at?->format('d M Y') }}</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-[11px] text-assocmap-secondary">Use View for barangay records and current association counts.</p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2" data-prevent-card-toggle>
                        <button type="button" aria-label="View municipality {{ $muni->name }}" data-area-view-url="{{ route('areas.municipalities.show', $muni->id) }}"
                                class="inline-flex items-center gap-1.5 rounded-md border border-assocmap-border bg-white px-3 py-1.5 text-xs font-semibold text-assocmap-text hover:bg-assocmap-bg">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
                            </svg>
                            View
                        </button>

                        <button type="button" aria-label="Edit municipality {{ $muni->name }}" @disabled($muni->is_archived) title="{{ $muni->is_archived ? 'Restore before editing' : 'Edit municipality' }}" data-municipality-modal-open="edit" data-municipality='@json($muniPayload)'
                                class="inline-flex items-center gap-1.5 rounded-md border border-assocmap-border bg-white px-3 py-1.5 text-xs font-semibold text-assocmap-text hover:bg-assocmap-bg">
                            Edit
                        </button>

                        <form id="muni-toggle-form-{{ $muni->id }}" action="{{ route('areas.municipalities.' . ($muni->is_archived ? 'restore' : 'archive'), $muni->id) }}" method="POST" class="hidden">
                            @csrf
                            @method('PATCH')
                        </form>
                        <button type="button"
                                data-confirm-open
                                data-confirm-target="muni-toggle-form-{{ $muni->id }}"
                                data-confirm-title="{{ $muni->is_archived ? 'Restore Municipality?' : 'Archive Municipality?' }}"
                                data-confirm-message="{{ $muni->is_archived ? 'This municipality will become current again.' : 'This municipality will be archived. It cannot be archived if current barangays or associations still reference it.' }}"
                                data-confirm-label="{{ $muni->is_archived ? 'Restore' : 'Archive' }}"
                                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold {{ $muni->is_archived ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-red-50 text-red-700 hover:bg-red-100' }}">
                            {{ $muni->is_archived ? 'Restore' : 'Archive' }}
                        </button>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-assocmap-border bg-white p-10 text-center shadow-card">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-assocmap-bg text-assocmap-primary">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><path d="M12 12.3a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6Z"/>
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-assocmap-text">No municipalities found</h3>
                    <p class="mt-1 text-sm text-assocmap-secondary">Try adjusting your filters or add the first coverage area.</p>
                </div>
            @endforelse
        </div>

        <div class="am-area-pagination mt-5">{{ $municipalities->links() }}</div>
    </section>

    {{-- ================= BARANGAYS PANEL ================= --}}
    <section id="am-panel-barangays" role="tabpanel" aria-labelledby="am-tab-barangays" data-am-tab-panel="barangays" class="hidden">

        <form data-area-filters="barangays" method="GET" action="{{ route('areas.index') }}" class="mb-6 rounded-xl border border-assocmap-border bg-white p-4 shadow-card">
            <input type="hidden" name="tab" value="barangays">
            @foreach (['search', 'status', 'muni_sort'] as $key)
                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">
            @endforeach
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label for="area-filter-brgy_search" class="text-xs font-medium text-assocmap-secondary">Search</label>
                    <input type="text" id="area-filter-brgy_search" name="brgy_search" value="{{ $filters['brgy_search'] ?? '' }}" placeholder="Search barangay..."
                           class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                </div>
                <div>
                    <label for="area-filter-area_unit_id" class="text-xs font-medium text-assocmap-secondary">Municipality</label>
                    <select id="area-filter-area_unit_id" name="area_unit_id" aria-describedby="area-filter-parent-preview" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                        <option value="">All Municipalities</option>
                        @foreach ($filterMunicipalities as $muniOption)
                            <option value="{{ $muniOption->id }}" @selected(($filters['area_unit_id'] ?? '') == $muniOption->id)>{{ $muniOption->name }}{{ $muniOption->is_archived ? ' (Archived)' : '' }}</option>
                        @endforeach
                    </select>
                    <p id="area-filter-parent-preview" data-area-value-preview="area-filter-area_unit_id" hidden class="am-area-name mt-1 text-xs text-assocmap-secondary"></p>
                </div>
                <div>
                    <label for="area-filter-brgy_status" class="text-xs font-medium text-assocmap-secondary">Status</label>
                    <select id="area-filter-brgy_status" name="brgy_status" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(($filters['brgy_status'] ?? '') === 'active')>Current</option>
                        <option value="archived" @selected(($filters['brgy_status'] ?? '') === 'archived')>Archived</option>
                    </select>
                </div>
                <div>
                    <label for="area-filter-brgy_sort" class="text-xs font-medium text-assocmap-secondary">Sort By</label>
                    <select id="area-filter-brgy_sort" name="brgy_sort" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                        <option value="name" @selected(($filters['brgy_sort'] ?? 'name') === 'name')>Name</option>
                        <option value="created_at" @selected(($filters['brgy_sort'] ?? '') === 'created_at')>Newest Created</option>
                        <option value="updated_at" @selected(($filters['brgy_sort'] ?? '') === 'updated_at')>Recently Updated</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-assocmap-border pt-4">
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-assocmap-primary px-4 py-2 text-sm font-semibold text-white hover:bg-assocmap-hover">
                        Apply
                    </button>
                    <a href="{{ route('areas.index', array_merge(\Illuminate\Support\Arr::only($filters, ['search', 'status', 'muni_sort', 'muni_page', 'per_page']), ['tab' => 'barangays'])) }}" class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                        Reset
                    </a>
                </div>
                <button type="button" data-barangay-modal-open="create"
                        class="rounded-lg bg-assocmap-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-assocmap-hover">
                    + Add Barangay
                </button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-assocmap-secondary">
                <label for="barangays-per-page">Rows per page</label>
                <select id="barangays-per-page" name="per_page" class="rounded border border-assocmap-border p-1">@foreach ([12, 24, 48] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 12) == $size)>{{ $size }}</option>@endforeach</select>
                <span>Showing {{ $barangays->firstItem() ?? 0 }}–{{ $barangays->lastItem() ?? 0 }} of {{ $barangays->total() }} matching records</span>
            </div>
</form>

        @include('admin-pages.admin-area-management.filter-chips', ['tab' => 'barangays'])
        <div class="hidden overflow-x-auto rounded-xl border border-assocmap-border bg-white shadow-card md:block">
            <table class="am-area-table min-w-full divide-y divide-assocmap-border text-sm">
                <caption class="sr-only">Barangay records matching the selected filters</caption>
                <thead class="bg-assocmap-bg">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Barangay</th>
                        <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Municipality</th>
                        <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Associations</th>
                        <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Created</th>
                        <th class="px-4 py-3 text-right font-semibold text-assocmap-text">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-assocmap-border">
                    @forelse ($barangays as $brgy)
                        @php
                            $brgyPayload = [
                                'id' => $brgy->id,
                                'name' => $brgy->name,
                                'area_unit_id' => $brgy->area_unit_id,
                            ];
                        @endphp
                        <tr class="hover:bg-assocmap-bg/40">
                            <td class="px-4 py-3 font-medium text-assocmap-text">{{ $brgy->name }}</td>
                            <td class="px-4 py-3 text-assocmap-secondary">{{ $brgy->area_unit_name }}</td>
                            <td class="px-4 py-3 text-assocmap-secondary">{{ (int) ($brgy->association_count ?? 0) }}</td>
                            <td class="px-4 py-3">
                                @if ($brgy->is_archived)
                                    <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Archived</span>
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Current</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-assocmap-secondary">{{ $brgy->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="relative inline-block text-left" data-am-dropdown>
                                    <button type="button" data-am-dropdown-toggle aria-label="Actions for {{ $brgy->name }}" aria-expanded="false" aria-controls="barangay-actions-{{ $brgy->id }}"
                                            class="rounded-md border border-assocmap-border p-1.5 text-assocmap-text hover:bg-assocmap-bg">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                                        </svg>
                                    </button>

                                    <div data-am-dropdown-menu id="barangay-actions-{{ $brgy->id }}"
                                         class="absolute right-0 z-10 mt-1 hidden w-44 rounded-lg border border-assocmap-border bg-white py-1 shadow-lg">
                                        <button type="button"
                                                data-brgy-view-url="{{ route('areas.barangays.show', $brgy->id) }}"
                                                class="block w-full px-3 py-2 text-left text-xs font-medium text-assocmap-text hover:bg-assocmap-bg">
                                            View Details
                                        </button>
                                        <button type="button"
                                                @disabled($brgy->is_archived) title="{{ $brgy->is_archived ? 'Restore before editing' : 'Edit barangay' }}" data-barangay-modal-open="edit"
                                                data-barangay='@json($brgyPayload)'
                                                class="block w-full px-3 py-2 text-left text-xs font-medium text-assocmap-text hover:bg-assocmap-bg">
                                            Edit
                                        </button>

                                        <form id="brgy-toggle-form-{{ $brgy->id }}" action="{{ route('areas.barangays.' . ($brgy->is_archived ? 'restore' : 'archive'), $brgy->id) }}" method="POST" class="hidden">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <button type="button"
                                                data-confirm-open
                                                data-confirm-target="brgy-toggle-form-{{ $brgy->id }}"
                                                data-confirm-title="{{ $brgy->is_archived ? 'Restore Barangay?' : 'Archive Barangay?' }}"
                                                data-confirm-message="{{ $brgy->is_archived ? 'This barangay will become current again.' : 'This barangay will be archived. Current association references must be resolved first.' }}"
                                                data-confirm-label="{{ $brgy->is_archived ? 'Restore' : 'Archive' }}"
                                                class="block w-full px-3 py-2 text-left text-xs font-medium {{ $brgy->is_archived ? 'text-green-600' : 'text-red-600' }} hover:bg-assocmap-bg">
                                            {{ $brgy->is_archived ? 'Restore' : 'Archive' }}
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-assocmap-secondary">
                                No barangays found. Try adjusting your filters or add a new one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 md:hidden">
            @forelse ($barangays as $brgy)
                @php
                    $brgyPayloadMobile = [
                        'id' => $brgy->id,
                        'name' => $brgy->name,
                        'area_unit_id' => $brgy->area_unit_id,
                    ];
                @endphp
                <div class="rounded-xl border border-assocmap-border bg-white p-4 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-assocmap-text">{{ $brgy->name }}</p>
                            <p class="text-xs text-assocmap-secondary">{{ $brgy->area_unit_name }} - {{ (int) ($brgy->association_count ?? 0) }} assoc.</p>
                        </div>
                        @if ($brgy->is_archived)
                            <span class="inline-flex flex-shrink-0 rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Archived</span>
                        @else
                            <span class="inline-flex flex-shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Current</span>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" aria-label="View barangay {{ $brgy->name }}" data-brgy-view-url="{{ route('areas.barangays.show', $brgy->id) }}"
                                class="flex-1 rounded-md border border-assocmap-border px-3 py-1.5 text-xs font-semibold text-assocmap-text">
                            View
                        </button>
                        <button type="button" aria-label="Edit barangay {{ $brgy->name }}" @disabled($brgy->is_archived) title="{{ $brgy->is_archived ? 'Restore before editing' : 'Edit barangay' }}" data-barangay-modal-open="edit" data-barangay='@json($brgyPayloadMobile)'
                                class="flex-1 rounded-md border border-assocmap-border px-3 py-1.5 text-xs font-semibold text-assocmap-text">
                            Edit
                        </button>
                        <form id="brgy-toggle-form-m-{{ $brgy->id }}" action="{{ route('areas.barangays.' . ($brgy->is_archived ? 'restore' : 'archive'), $brgy->id) }}" method="POST" class="hidden">
                            @csrf
                            @method('PATCH')
                        </form>
                        <button type="button"
                                data-confirm-open
                                data-confirm-target="brgy-toggle-form-m-{{ $brgy->id }}"
                                data-confirm-title="{{ $brgy->is_archived ? 'Restore Barangay?' : 'Archive Barangay?' }}"
                                data-confirm-message="{{ $brgy->is_archived ? 'This barangay will become current again.' : 'This barangay will be archived. Current association references must be resolved first.' }}"
                                data-confirm-label="{{ $brgy->is_archived ? 'Restore' : 'Archive' }}"
                                class="flex-1 rounded-md border border-assocmap-border px-3 py-1.5 text-xs font-semibold {{ $brgy->is_archived ? 'text-green-600' : 'text-red-600' }}">
                            {{ $brgy->is_archived ? 'Restore' : 'Archive' }}
                        </button>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-assocmap-border bg-white p-6 text-center text-sm text-assocmap-secondary">
                    No barangays found.
                </p>
            @endforelse
        </div>

        <div class="am-area-pagination mt-5">{{ $barangays->links() }}</div>
    </section>

    @include('admin-pages.admin-area-management.modals')

</div>
</x-dashboard-layout>
