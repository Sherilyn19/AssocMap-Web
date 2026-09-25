{{--
    resources/views/admin-pages/admin-area-management/admin-area-index.blade.php
    Area Management Module - System Administrator view only.
--}}

<x-dashboard-layout title="Area Management" topbar-title="Area Management">
<div data-area-management-page data-management-register>
{{-- Keep each form's errors and unsaved input separate from the other form. --}}
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

    @include('admin-pages.admin-area-management.page-header')

{{-- Summary cards show totals for all records, regardless of list filters.
         Each card links to its matching records. --}}
    @php
        $summaryCards = [
            [
                'key' => 'municipalities',
                'url' => route('areas.index', ['tab' => 'municipalities']),
                'label' => 'Total Municipalities',
                'value' => $summary['total_municipalities'],
                'note' => $summary['active_municipalities'] . ' current · ' . $summary['archived_municipalities'] . ' archived',
            ],
            [
                'key' => 'associations',
                'url' => route('admin.associations.index', ['archive_state' => 'current']),
                'label' => 'Current Associations',
                'value' => $summary['total_associations'],
                'note' => 'Includes operationally Active and Inactive records',
            ],
            [
                'key' => 'barangays',
                'url' => route('areas.index', ['tab' => 'barangays', 'brgy_status' => 'active']),
                'label' => 'Current Barangays',
                'value' => $summary['active_barangays'],
                'note' => $summary['total_barangays'] . ' total records',
            ],
            [
                'key' => 'coverage',
                'url' => route('areas.index', ['tab' => 'barangays', 'brgy_status' => 'archived']),
                'label' => 'Archived Barangays',
                'value' => $summary['archived_barangays'],
                'note' => 'Historical reference records',
            ],
        ];
    @endphp

{{-- Show the label, count, and note in the same order as other modules.
         Equal-width digits make counts easier to read. Standard links support
         keyboard use and opening records in a new tab. --}}
    <section class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
             aria-label="Area summary — all records, independent of filters">
        @foreach ($summaryCards as $card)
            <a href="{{ $card['url'] }}" data-am-summary-card="{{ $card['key'] }}"
               aria-label="{{ $card['label'] }}: {{ $card['value'] }}. View matching records."
               aria-describedby="area-summary-note-{{ $card['key'] }}"
               class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                <p class="text-sm font-medium text-slate-600">{{ $card['label'] }}</p>
                <p class="mt-2 break-words text-3xl font-bold tabular-nums text-slate-900">{{ $card['value'] }}</p>
                <p id="area-summary-note-{{ $card['key'] }}" class="mt-1 text-xs text-slate-500">{{ $card['note'] }}</p>
            </a>
        @endforeach
    </section>

    <p class="mb-6 text-xs text-slate-500">Summary totals include all records, independent of the list filters. Select a card to open its matching records.</p>

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

{{-- Clear filter and record headings help users find each section.
             Keep headings in h1, h2, h3 order for screen reader navigation. --}}
        <form data-area-filters="municipalities" aria-labelledby="am-municipality-filters-title" method="GET" action="{{ route('areas.index') }}" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 id="am-municipality-filters-title" class="mb-4 text-lg font-bold text-slate-900">Filter Municipalities</h2>
{{-- Leave page numbers out of the filter form so filter or page-size changes
                 start on page one. Page links keep the valid filters selected. --}}
            <input type="hidden" name="tab" value="municipalities">
            @foreach (['brgy_search', 'brgy_status', 'brgy_sort', 'area_unit_id'] as $key)
                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">
            @endforeach
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div>
                    <label for="area-filter-search" class="text-sm font-medium text-slate-700">Search</label>
                    <input type="text" id="area-filter-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search municipality or address..."
                           class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <div>
                    <label for="area-filter-status" class="text-sm font-medium text-slate-700">Status</label>
                    <select id="area-filter-status" name="status" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Current</option>
                        <option value="archived" @selected(($filters['status'] ?? '') === 'archived')>Archived</option>
                    </select>
                </div>
                <div>
                    <label for="area-filter-muni_sort" class="text-sm font-medium text-slate-700">Sort By</label>
                    <select id="area-filter-muni_sort" name="muni_sort" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="name" @selected(($filters['muni_sort'] ?? 'name') === 'name')>Name</option>
                        <option value="created_at" @selected(($filters['muni_sort'] ?? '') === 'created_at')>Newest Created</option>
                        <option value="updated_at" @selected(($filters['muni_sort'] ?? '') === 'updated_at')>Recently Updated</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        Apply
                    </button>
                    <a href="{{ route('areas.index', array_merge(\Illuminate\Support\Arr::only($filters, ['brgy_search', 'brgy_status', 'brgy_sort', 'area_unit_id', 'brgy_page', 'per_page']), ['tab' => 'municipalities'])) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        Reset
                    </a>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                <label for="municipalities-per-page">Rows per page</label>
                <select id="municipalities-per-page" name="per_page" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200">@foreach ([12, 24, 48] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 12) == $size)>{{ $size }}</option>@endforeach</select>
            </div>
        </form>

{{-- Keep Add beside the records and the filter form above the results.
             Display the supplied records and use their existing page links. --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="am-municipality-records-title">
            <header class="space-y-3 border-b border-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="am-municipality-records-title" class="text-lg font-bold text-slate-900">Municipality Records</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ number_format($municipalities->total()) }} matching records</p>
                    </div>
                    <button type="button" data-municipality-modal-open="create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">+ Add Municipality</button>
                </div>
                @include('admin-pages.admin-area-management.filter-chips', ['tab' => 'municipalities'])
            </header>
            <div class="p-4 sm:p-5">
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

{{-- Use separate buttons to expand the card and perform record actions.
                             Disable the card transform so the More menu stays positioned
                             relative to the screen. --}}
                        <article data-area-card="municipality-detail-{{ $muni->id }}"
                                 aria-labelledby="municipality-name-{{ $muni->id }}" class="am-area-card !transform-none">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 id="municipality-name-{{ $muni->id }}" class="am-area-name text-base font-semibold text-slate-900">{{ $muni->name }}</h3>
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

                            <div class="mt-4">
                                @include('admin-pages.admin-area-management.actions', [
                                    'entity' => 'municipality', 'record' => $muni, 'payload' => $muniPayload,
                                    'formId' => 'muni-toggle-form-' . $muni->id,
                                ])
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-assocmap-border bg-white p-10 text-center shadow-card">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-assocmap-bg text-assocmap-primary">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><path d="M12 12.3a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6Z"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">No municipalities found</h3>
                            <p class="mt-1 text-sm text-assocmap-secondary">Try adjusting your filters or add the first coverage area.</p>
                        </div>
                    @endforelse
                </div>
            </div>

{{-- Use the shared page buttons. Municipality page links keep the selected
                 tab and filters. Show the record range even when there is only one page. --}}
            <div class="overflow-hidden rounded-b-xl">
                <x-management-pagination :records="$municipalities" :numbered="true" label="Municipality pagination" />
            </div>
        </section>
    </section>

    {{-- ================= BARANGAYS PANEL ================= --}}
    <section id="am-panel-barangays" role="tabpanel" aria-labelledby="am-tab-barangays" data-am-tab-panel="barangays" class="hidden">

        <form data-area-filters="barangays" aria-labelledby="am-barangay-filters-title" method="GET" action="{{ route('areas.index') }}" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 id="am-barangay-filters-title" class="mb-4 text-lg font-bold text-slate-900">Filter Barangays</h2>
            <input type="hidden" name="tab" value="barangays">
            @foreach (['search', 'status', 'muni_sort'] as $key)
                <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">
            @endforeach
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label for="area-filter-brgy_search" class="text-sm font-medium text-slate-700">Search</label>
                    <input type="text" id="area-filter-brgy_search" name="brgy_search" value="{{ $filters['brgy_search'] ?? '' }}" placeholder="Search barangay..."
                           class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <div>
                    <label for="area-filter-area_unit_id" class="text-sm font-medium text-slate-700">Municipality</label>
                    <select id="area-filter-area_unit_id" name="area_unit_id" aria-describedby="area-filter-parent-preview" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All Municipalities</option>
                        @foreach ($filterMunicipalities as $muniOption)
                            <option value="{{ $muniOption->id }}" @selected(($filters['area_unit_id'] ?? '') == $muniOption->id)>{{ $muniOption->name }}{{ $muniOption->is_archived ? ' (Archived)' : '' }}</option>
                        @endforeach
                    </select>
                    <p id="area-filter-parent-preview" data-area-value-preview="area-filter-area_unit_id" hidden class="am-area-name mt-1 text-xs text-slate-600"></p>
                </div>
                <div>
                    <label for="area-filter-brgy_status" class="text-sm font-medium text-slate-700">Status</label>
                    <select id="area-filter-brgy_status" name="brgy_status" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(($filters['brgy_status'] ?? '') === 'active')>Current</option>
                        <option value="archived" @selected(($filters['brgy_status'] ?? '') === 'archived')>Archived</option>
                    </select>
                </div>
                <div>
                    <label for="area-filter-brgy_sort" class="text-sm font-medium text-slate-700">Sort By</label>
                    <select id="area-filter-brgy_sort" name="brgy_sort" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="name" @selected(($filters['brgy_sort'] ?? 'name') === 'name')>Name</option>
                        <option value="created_at" @selected(($filters['brgy_sort'] ?? '') === 'created_at')>Newest Created</option>
                        <option value="updated_at" @selected(($filters['brgy_sort'] ?? '') === 'updated_at')>Recently Updated</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        Apply
                    </button>
                    <a href="{{ route('areas.index', array_merge(\Illuminate\Support\Arr::only($filters, ['search', 'status', 'muni_sort', 'muni_page', 'per_page']), ['tab' => 'barangays'])) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                        Reset
                    </a>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                <label for="barangays-per-page">Rows per page</label>
                <select id="barangays-per-page" name="per_page" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200">@foreach ([12, 24, 48] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 12) == $size)>{{ $size }}</option>@endforeach</select>
            </div>
        </form>

{{-- Keep Add beside the records and the filter form above the results.
             Display the supplied records and use their existing page links. --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="am-barangay-records-title">
            <header class="space-y-3 border-b border-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="am-barangay-records-title" class="text-lg font-bold text-slate-900">Barangay Records</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ number_format($barangays->total()) }} matching records</p>
                    </div>
                    <button type="button" data-barangay-modal-open="create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">+ Add Barangay</button>
                </div>
                @include('admin-pages.admin-area-management.filter-chips', ['tab' => 'barangays'])
            </header>
            <div class="p-4 sm:p-5">
                <div class="hidden overflow-x-auto rounded-xl border border-assocmap-border bg-white shadow-card md:block">
{{-- Leave enough space for View, Edit, and More buttons. Smaller screens
                         use horizontal scrolling or mobile cards. --}}
                    <table class="am-area-table !min-w-[56rem] divide-y divide-assocmap-border text-sm">
                        <caption class="sr-only">Barangay records matching the selected filters</caption>
                        <thead class="bg-assocmap-bg">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Barangay</th>
                                <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Municipality</th>
                                <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Associations</th>
                                <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Created</th>
                                <th class="w-56 px-4 py-3 text-right font-semibold text-assocmap-text">Actions</th>
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
                                        @include('admin-pages.admin-area-management.actions', [
                                            'entity' => 'barangay', 'record' => $brgy, 'payload' => $brgyPayload,
                                            'formId' => 'brgy-toggle-form-' . $brgy->id, 'alignRight' => true,
                                        ])
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
                                    <h3 class="am-area-name text-base font-semibold text-slate-900">{{ $brgy->name }}</h3>
                                    <p class="text-xs text-assocmap-secondary">{{ $brgy->area_unit_name }} - {{ (int) ($brgy->association_count ?? 0) }} assoc.</p>
                                </div>
                                @if ($brgy->is_archived)
                                    <span class="inline-flex flex-shrink-0 rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Archived</span>
                                @else
                                    <span class="inline-flex flex-shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Current</span>
                                @endif
                            </div>
                            <div class="mt-3">
                                @include('admin-pages.admin-area-management.actions', [
                                    'entity' => 'barangay', 'record' => $brgy, 'payload' => $brgyPayloadMobile,
                                    'formId' => 'brgy-toggle-form-m-' . $brgy->id,
                                ])
                            </div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-assocmap-border bg-white p-6 text-center text-sm text-assocmap-secondary">
                            No barangays found.
                        </p>
                    @endforelse
                </div>
            </div>

{{-- Keep barangay and municipality page numbers separate.
                 Barangay page links keep the selected tab and filters. --}}
            <div class="overflow-hidden rounded-b-xl">
                <x-management-pagination :records="$barangays" :numbered="true" label="Barangay pagination" />
            </div>
        </section>
    </section>

    @include('admin-pages.admin-area-management.modals')

</div>
</x-dashboard-layout>
