<x-dashboard-layout title="Assigned Areas">
<div class="fo-coverage space-y-6" data-officer-areas>
    {{-- The existing layout supplies navigation and the signed-in identity. --}}
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">Assigned Areas</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Explore the geographic coverage of your assigned associations.
            </p>
        </div>

        {{-- Dark navy matches the modal header; the map icon is decorative. --}}
        <a href="{{ route('gis.officer.index') }}"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg
                bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white
                transition-colors duration-200 hover:bg-slate-800
                focus-visible:outline focus-visible:outline-2
                focus-visible:outline-offset-2 focus-visible:outline-slate-900">

            {{-- Heroicons: map --}}
            <svg class="h-5 w-5 shrink-0"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 6.75V21m0-14.25L15 3m-6 3.75L3 3v14.25L9 21m6-18v14.25m0-14.25 6 3.75V21l-6-3.75m0 0L9 21"/>
            </svg>

            <span>Open GIS Mapping</span>
        </a>
    </header>

    {{-- Overall totals remain stable when table filters change. --}}
    <section aria-label="Overall assigned coverage"
             class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'municipalities' => 'Municipalities covered',
            'barangays' => 'Barangays covered',
            'associations' => 'Assigned associations',
            'issues' => 'Area information issues',
        ] as $key => $label)
            <div class="fo-summary-card {{ $key === 'issues' ? 'fo-summary-warning' : '' }}">
                <p class="text-sm font-medium uppercase 1 text-slate-600">{{ $label }}</p>
                <p data-count-up class="mt-3 text-3xl font-bold tabular-nums text-slate-900">
                    {{ number_format($summary[$key]) }}
                </p>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    {{ $key === 'issues'
                        ? 'Missing or inconsistent geographic records.'
                        : 'Across all assigned associations, including archived records.' }}
                </p>
            </div>
        @endforeach
    </section>

    {{-- Filters apply only to the signed-in officer's assigned associations. --}}
    <form method="GET"
        action="{{ route('officer.areas.index') }}"
        class="fo-filter-card">

        <div class="fo-section-heading">
            <div>
                <h2 class="flex items-center gap-2 font-semibold">
                    {{-- Filter adjustment icon. --}}
                    <svg class="h-5 w-5 text-teal-800"
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5">
                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 6h3m4 0h11M3 12h11m4 0h3M3 18h3m4 0h11M6 4v4m8 2v4M6 16v4"/>
                    </svg>
                    Filter assigned coverage
                </h2>
            </div>

            <span class="fo-pill fo-pill-teal">Your assignments only</span>
        </div>

        <div class="grid gap-4 px-5 py-3 lg:grid-cols-3">
            <label class="flex min-w-0 flex-col gap-2">
                <span>Search</span>
                <input type="search"
                    name="search"
                    maxlength="150"
                    value="{{ $search }}"
                    placeholder="Association, municipality, barangay">
            </label>

            <label class="flex min-w-0 flex-col gap-2">
                <span>Association archive state</span>
                <select name="archive">
                    <option value="current" @selected($archive === 'current')>
                        Not archived
                    </option>
                    <option value="archived" @selected($archive === 'archived')>
                        Archived
                    </option>
                    <option value="all" @selected($archive === 'all')>
                        All assigned associations
                    </option>
                </select>
            </label>

            <label class="flex min-w-0 flex-col gap-2">
                <span>Geographic information</span>
                <select name="quality">
                    <option value="all" @selected($quality === 'all')>
                        All records
                    </option>
                    <option value="incomplete" @selected($quality === 'incomplete')>
                        Needs review
                    </option>
                </select>
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
            <p class="text-sm text-slate-500">
                {{ $areas->total() }} matching municipalities
            </p>

            <div class="flex gap-2">
                <a href="{{ route('officer.areas.index') }}" class="fo-action">
                    Reset
                </a>
                <button type="submit" class="fo-primary am-button-green">
                    Apply filters
                </button>
            </div>
        </div>

        {{-- Display validation feedback without hiding the filters. --}}
        @if($errors->any())
            <div role="alert" class="fo-warning m-5">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
    </form>

    @if($issues->isNotEmpty())
        {{-- Missing information stays visible even without a municipality. --}}
        <details class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <summary class="min-h-11 cursor-pointer font-semibold text-amber-950">
                {{ $issues->count() }} matching associations need area review
            </summary>
            <p class="mt-2 text-sm leading-6 text-amber-900">
                Geographic records are maintained by the System Administrator.
                Missing barangays and municipality mismatches are excluded from
                barangay coverage counts.
            </p>

            <ul class="mt-4 divide-y divide-amber-200">
                @foreach($issues as $association)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-semibold">{{ $association->name }}</p>
                            <p class="mt-1 text-sm">
                                {{ $association->areaUnit?->name ?? 'Municipality not recorded' }}
                                · {{ $association->subUnit?->name ?? 'Barangay not recorded' }}
                            </p>
                            @if($association->subUnit && $association->areaUnit
                                && (int) $association->subUnit->area_unit_id
                                    !== (int) $association->area_unit_id)
                                <p class="mt-1 text-sm font-medium">
                                    Barangay belongs to a different municipality.
                                </p>
                            @endif
                        </div>
                        <a class="inline-flex min-h-11 items-center font-semibold underline"
                           href="{{ route('officer.associations.show', $association->id) }}">
                            View association
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold">Municipality coverage</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                Table totals follow your filters. Members and projects count
                non-archived records in the matching assigned associations.
            </p>
        </div>

        <div class="overflow-x-auto" tabindex="0"
             role="region" aria-label="Municipality coverage table">
            <table class="w-full min-w-[780px] text-left text-sm">
                <caption class="sr-only">Filtered coverage of assigned associations</caption>
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        @foreach(['Municipality', 'Barangays', 'Assigned associations',
                            'Members', 'Projects', 'Area issues', 'Details'] as $heading)
                            <th scope="col" class="px-5 py-4 font-semibold">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($areas as $area)
                        <tr class="hover:bg-slate-50">
                            <th scope="row" class="px-5 py-4 font-semibold">
                                {{ $area['name'] }}
                                @if($area['archived'])
                                    <span class="mt-1 block text-xs font-normal text-amber-800">
                                        Geographic record archived
                                    </span>
                                @endif
                            </th>
                            <td class="px-5 py-4">
                                <strong>{{ $area['barangays']->count() }}</strong>
                                <p class="mt-1 max-w-48 text-xs leading-5 text-slate-500">
                                    {{ $area['barangays']->take(3)->implode(', ') ?: 'Not recorded' }}
                                    @if($area['barangays']->count() > 3)
                                        +{{ $area['barangays']->count() - 3 }} more
                                    @endif
                                </p>
                            </td>
                            @foreach(['associations', 'members', 'projects', 'issues'] as $column)
                                <td class="px-5 py-4 tabular-nums">
                                    {{ number_format($area[$column]) }}
                                </td>
                            @endforeach
                            <td class="px-5 py-4">
                                {{-- Works as a normal link when JavaScript is unavailable. --}}
                                <a data-area-details
                                   href="{{ route('officer.areas.show', $area['id']) }}"
                                   class="am-user-button am-user-button-secondary whitespace-nowrap"
                                   aria-label="View coverage for {{ $area['name'] }}">
                                    View coverage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                                {{ $summary['associations'] === 0
                                    ? 'No associations are currently assigned to you.'
                                    : 'No municipalities match these filters. Check area issues or reset the filters.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-management-pagination :records="$areas" label="Municipality pagination" />
    </section>

    {{-- A native dialog supplies keyboard containment and Escape dismissal. --}}
    {{-- One native dialog contains the coverage table and its related-record drawer. --}}
    <dialog data-area-dialog class="fo-dialog" aria-labelledby="area-dialog-title">
        <header class="fo-dialog-header">
            <div>
                <p class="fo-eyebrow">Field Officer workspace</p>
                <h2 id="area-dialog-title">Assigned coverage</h2>
            </div>
            <button data-area-close type="button" class="fo-dialog-close">
                Close
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/>
                </svg>
            </button>
        </header>

        <div class="fo-workspace">
            <div data-area-content class="fo-main-panel" aria-live="polite"></div>

            <aside data-area-side data-context-drawer
                class="fo-side-panel" hidden inert
                aria-labelledby="area-drawer-title">
                <header class="fo-side-header am-drawer-header">
                    <h3 id="area-drawer-title"
                        data-drawer-heading
                        data-base-title="Association details">
                        Association details
                    </h3>
                    <button data-drawer-close type="button"
                            class="fo-action">
                        Close panel
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </header>
                <div data-drawer-content data-drawer-scroll class="fo-side-body" aria-live="polite">
            </aside>
        </div>
    </dialog>
</div>
</x-dashboard-layout>