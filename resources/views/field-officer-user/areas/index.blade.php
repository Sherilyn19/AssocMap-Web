@php
    $memberAreaView = request()->routeIs('member.areas.*');
    $areaRoutes = $memberAreaView ? 'member.areas' : 'officer.areas';
    $areaPageTitle = $memberAreaView ? 'View Area Records' : 'Assigned Areas';
    $areaMapRoute = $memberAreaView ? 'gis.viewer' : 'gis.officer.index';
@endphp

<x-dashboard-layout :title="$areaPageTitle">
<div class="fo-coverage am-area-page space-y-6" data-officer-areas>
    {{-- The existing layout supplies navigation and the signed-in identity. --}}
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">
                {{ $areaPageTitle }}
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                {{ $memberAreaView
                ? 'Explore the area records, members, projects, and published locations of your association.'
                : 'Explore the geographic coverage of your assigned associations.' }}
            </p>
        </div>

        {{-- Dark navy matches the modal header; the map icon is decorative. --}}
        <a href="{{ route($areaMapRoute) }}"
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

    @php
        // Keep descriptions out of the visual card while retaining accessible help.
        $analyticsLabels = [
            'municipalities' => 'Cities / Municipalities',
            'barangays' => 'Barangays',
            'associations' => 'Associations',
            'issues' => 'Geographic record issues',
        ];

        $analyticsHelp = [
            'municipalities' => 'Distinct recorded municipalities in your current filtered results. Access remains limited to associations available to your account.',
            'barangays' => 'Distinct barangays in your filtered results with a valid municipality relationship. Missing or mismatched geographic records are excluded.',
            'associations' => 'Associations matching your current search, archive state, and geographic-information filters.',
            'issues' => 'Matching associations with missing municipality or barangay records, or a barangay linked to another municipality. Zero issues does not verify field addresses, GPS coordinates, or boundaries.',
        ];
    @endphp

    <section class="area-analytics" aria-label="Filtered area coverage">
        @foreach($analyticsLabels as $key => $label)
            <article class="area-metric {{ $key === 'issues' ? 'area-metric--warning' : '' }}">
                {{-- Keep the main action and information button as separate controls. --}}
                <button type="button"
                        class="area-metric__open"
                        data-area-metric="{{ $key }}"
                        aria-haspopup="dialog"
                        aria-controls="area-analytics-dialog">
                    <span class="area-metric__label">{{ $label }}</span>
                    <strong class="area-metric__value">
                        {{ number_format($summary[$key]) }}
                    </strong>
                    <span class="area-metric__link">
                        View details <span aria-hidden="true">↗</span>
                    </span>
                </button>

                {{-- Heroicons outline information-circle, accessible by hover, focus, or tap. --}}
                <button type="button"
                        class="area-metric__info"
                        data-area-help="area-help-{{ $key }}"
                        aria-label="About {{ strtolower($label) }}"
                        aria-describedby="area-help-{{ $key }}"
                        aria-expanded="false">
                    <svg width="22" height="22" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.5"
                        aria-hidden="true" focusable="false">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.852l.041-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 8.25h.008v.008H12V8.25Z"/>
                    </svg>
                </button>

                {{-- Popovers use the browser top layer, avoiding clipping and layout shifts. --}}
                <div id="area-help-{{ $key }}"
                    class="area-help"
                    role="tooltip"
                    popover="manual">
                    {{ $analyticsHelp[$key] }}
                </div>
            </article>

            {{-- Blade escapes all record text before it enters the modal. --}}
            <template id="area-metric-template-{{ $key }}">
                <p class="area-analytics-context">Matches the current filters.</p>
                @forelse($analytics[$key] as $record)
                    <article class="area-analytics-record">
                        <h3>{{ $record['title'] }}</h3>
                        <p>{{ $record['detail'] }}</p>
                    </article>
                @empty
                    <p class="area-analytics-empty">No matching records.</p>
                @endforelse
            </template>
        @endforeach
    </section>

    {{-- One reusable read-only modal; existing coverage dialogs remain separate. --}}
    <dialog id="area-analytics-dialog"
            class="area-analytics-dialog"
            aria-labelledby="area-analytics-title">
        <header class="area-analytics-heading">
            <h2 id="area-analytics-title">Coverage details</h2>
            <button type="button" data-area-analytics-close
                    class="fo-action" autofocus>Close</button>
        </header>

        <div class="area-analytics-body" data-area-analytics-body></div>

        <footer class="area-analytics-footer">
            <button type="button" class="fo-primary am-button-green"
                    data-area-analytics-jump>
                View related records
            </button>
        </footer>
    </dialog>

    {{-- Filters apply only to the signed-in officer's assigned associations. --}}
    <form method="GET"
        action="{{ route($areaRoutes.'.index') }}"
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
                    {{ $memberAreaView ? 'Filter area records' : 'Filter assigned coverage' }}
                </h2>
            </div>

            <span class="fo-pill fo-pill-teal">{{ $memberAreaView ? 'Your association only' : 'Your assignments only' }}</span>
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
                        {{ $memberAreaView ? 'All archive states' : 'All assigned associations' }}
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
                <a href="{{ route($areaRoutes.'.index') }}" class="fo-action">
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
        {{-- The analytics action opens and highlights this issue list. --}}
        <details id="area-geographic-issues"
                class="rounded-xl border border-amber-200 bg-amber-50 p-5"
                tabindex="-1">
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
                                {{ $association->areaUnit?->name ?? 'City / Municipality not recorded' }}
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
                           href="{{ ($memberAreaView
                            ? route('member.information')
                            : route('officer.associations.show', $association->id)) }}">
                            View association
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Analytics actions focus and highlight this filtered coverage section. --}}
    <section id="area-filtered-coverage" tabindex="-1"
             class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold">City / Municipality coverage</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                Table totals follow your filters. Members and projects count
                non-archived records in the associations available to your account.
            </p>
        </div>

        <div class="overflow-x-auto" tabindex="0"
             role="region" aria-label="City / Municipality coverage table">
            <table class="w-full min-w-[780px] text-left text-sm">
                <caption class="sr-only">Filtered coverage of associations available to your account</caption>
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        @foreach(['City / Municipality', 'Barangays', 'Associations',
                            'Members', 'Projects', 'Area issues', 'Details'] as $heading)
                            <th scope="col" class="px-5 py-4 font-semibold">
                                <span class="inline-flex items-center gap-1">
                                    {{ $heading }}

                                    @if($heading === 'Area issues')
                                        {{-- Reuse the existing overlay tooltip behavior. --}}
                                        <button type="button"
                                                class="area-metric__info area-table-info"
                                                data-area-help="area-table-issues-help"
                                                aria-label="About area issues"
                                                aria-describedby="area-table-issues-help"
                                                aria-expanded="false">
                                            {{-- Heroicons outline: information-circle. --}}
                                            <svg width="18" height="18" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="1.5"
                                                aria-hidden="true" focusable="false">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.852l.041-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 8.25h.008v.008H12V8.25Z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </span>

                                @if($heading === 'Area issues')
                                    {{-- The top-layer bubble is not clipped by the scrollable table. --}}
                                    <div id="area-table-issues-help"
                                        class="area-help"
                                        role="tooltip"
                                        popover="manual">
                                        Number of matching associations in this municipality with missing
                                        or inconsistent geographic records. Zero means no missing
                                        municipality/barangay or municipality–barangay mismatch was detected.
                                        It does not verify addresses, GPS coordinates, or boundaries.
                                    </div>
                                @endif
                            </th>
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
                                   href="{{ route($areaRoutes.'.show', [
                                    'areaUnit' => $area['id'],
                                    'search' => $search,
                                    'archive' => $archive,
                                    'quality' => $quality,
                                ]) }}"
                                   class="am-user-button am-user-button-secondary whitespace-nowrap"
                                   aria-label="View coverage for {{ $area['name'] }}">
                                    View coverage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                                {{-- Filtered zero results do not imply that the account has no assignments. --}}
                                No municipalities match these filters. Check area issues or reset the filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-management-pagination :records="$areas" label="City / Municipality pagination" />
    </section>

    {{-- A native dialog supplies keyboard containment and Escape dismissal. --}}
    {{-- One native dialog contains the coverage table and its related-record drawer. --}}
    <dialog data-area-dialog class="fo-dialog" aria-labelledby="area-dialog-title">
        <header class="fo-dialog-header">
            <div>
                <p class="fo-eyebrow">
                    {{ $memberAreaView ? 'Association workspace' : 'Field Officer workspace' }}
                </p>
                <h2 id="area-dialog-title">
                    {{ $memberAreaView ? 'Association area records' : 'Assigned coverage' }}
                </h2>
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
                <div data-drawer-content data-drawer-scroll
                class="fo-side-body"
                aria-live="polite"></div>
            </aside>
        </div>
    </dialog>
</div>
</x-dashboard-layout>
