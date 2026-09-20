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

<x-dashboard-layout title="Association Management" topbar-title="Association Management">
<div
    class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8"
    data-management-register
    data-association-page data-records-url="{{ route('admin.associations.index') }}"
    data-recovery="{{ json_encode(session('association_form')) }}"
    data-filter-barangays="{{ json_encode($filterBarangays) }}"
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


    {{-- Global card counts remain independent of the filters applied below. --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Association summary — all records, independent of filters">
        @foreach($cardLabels as $key => $label)
            <a id="association-card-{{ $key }}" href="{{ route('admin.associations.index', [...$listState, 'summary' => $key]) }}#association-card-details"
               class="am-association-card rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
               aria-haspopup="dialog" aria-label="{{ $label }}: {{ $summary[$key] }}. View matching records.">
                <span class="block text-sm font-medium text-slate-600">{{ $label }}</span>
                <span class="mt-2 block text-3xl font-bold tabular-nums text-slate-900">{{ $summary[$key] }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ $key === 'total' ? 'Current and archived records' : ($key === 'archived' ? 'Historical records' : 'Current records only') }}</span>
            </a>
        @endforeach
    </section>
    @include('admin-pages.admin-association-management.partials.filters')
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="association-records-title">
        <header class="space-y-3 border-b border-slate-200 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="association-records-title" class="text-lg font-bold text-slate-900">Association Records</h2><p class="text-sm text-slate-600">{{ number_format($associations->total()) }} matching records</p></div>
            @php
                $applied = [];
                if (filled($filters['search'] ?? null)) $applied['search'] = 'Search: '.$filters['search'];
                foreach (['area_unit_id' => ['Municipality', $filterMunicipalities, 'name'], 'sub_unit_id' => ['Barangay', $filterBarangays, 'name'], 'program_component_id' => ['Component', $programComponents, 'name'], 'field_officer_id' => ['Officer', $filterOfficers, 'name'], 'status_id' => ['Status', $associationStatuses, 'status_name']] as $field => [$label, $options, $column]) {
                    if (filled($filters[$field] ?? null)) $applied[$field] = $label.': '.($options->firstWhere('id', $filters[$field])?->$column ?? 'Unavailable');
                }
                if (($filters['archive_state'] ?? 'current') !== 'current') $applied['archive_state'] = 'Records: '.ucfirst($filters['archive_state']);
                $sortLabels = ['name_desc' => 'Name Z–A', 'date_joined_desc' => 'Newest joined', 'date_joined_asc' => 'Oldest joined', 'created_desc' => 'Recently created', 'updated_desc' => 'Recently updated'];
                if (isset($sortLabels[$filters['sort'] ?? ''])) $applied['sort'] = 'Sort: '.$sortLabels[$filters['sort']];
            @endphp
            @if($applied)
                <nav class="flex flex-wrap gap-2" aria-label="Applied filters">
                    @foreach($applied as $key => $label)
                        @php $removeState = array_diff_key($listState, array_flip($key === 'area_unit_id' ? [$key, 'sub_unit_id', 'page'] : [$key, 'page'])); @endphp
                        <a href="{{ route('admin.associations.index', $removeState) }}" class="inline-flex min-h-10 items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700" aria-label="Remove {{ $label }}">{{ $label }} <span aria-hidden="true">×</span></a>
                    @endforeach
                </nav>
            @endif
        </header>
        @include('admin-pages.admin-association-management.partials.records')
        <x-management-pagination :records="$associations" :numbered="true" />
    </section>
    @if($summaryRecords)
        @include('admin-pages.admin-association-management.partials.card-details')
    @endif
    @include('admin-pages.admin-association-management.partials.form-modals')
    @include('admin-pages.admin-association-management.partials.confirmation')
</div>
</x-dashboard-layout>
