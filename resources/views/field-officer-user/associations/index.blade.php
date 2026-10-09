<x-dashboard-layout title="My Associations">
<div class="fo-coverage space-y-6" data-record-workspace>
    {{-- The shared layout supplies the same workspace header and navigation. --}}
    <header>
        <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
        <h1>My Associations</h1>
        <p class="mt-2 text-sm text-slate-600">
            View association profiles and program records within your assignments.
        </p>
    </header>

    @include('shared.membership.partials.feedback')

    {{-- Keep the existing filter names and their authorized database options. --}}
    <form method="GET"
          action="{{ route('officer.associations.index') }}"
          class="fo-filter-card">
        <div class="fo-section-heading">
            <h2 class="flex items-center gap-2 font-semibold">
                <svg class="h-5 w-5 text-teal-800"
                     viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     aria-hidden="true">
                    <path stroke-linecap="round"
                          d="M3 6h3m4 0h11M3 12h11m4 0h3M3 18h3m4 0h11M6 4v4m8 2v4M6 16v4"/>
                </svg>
                Filter associations
            </h2>
            <span class="fo-pill fo-pill-teal">Your assignments only</span>
        </div>

        <div class="grid gap-4 px-5 py-3 sm:grid-cols-2 xl:grid-cols-4">
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Search</span>
                <input type="search" name="search" maxlength="255"
                       value="{{ $filters['search'] ?? '' }}"
                       class="w-full"
                       placeholder="Association name">
            </label>

            @foreach([
                'area_unit_id' => ['City / Municipality', $areas, 'name'],
                'program_component_id' => ['Program component', $components, 'name'],
                'status_id' => ['Status', $statuses, 'status_name'],
            ] as $key => [$label, $options, $name])
                <label class="flex min-w-0 flex-col gap-2">
                    <span class="text-sm font-semibold">{{ $label }}</span>
                    <select name="{{ $key }}" class="w-full">
                        <option value="">All</option>

                        @foreach($options as $option)
                            <option value="{{ $option->id }}"
                                    @selected((string) ($filters[$key] ?? '') === (string) $option->id)>
                                {{ $option->$name }}
                            </option>
                        @endforeach

                        @if($key === 'status_id')
                            <option value="archived"
                                    @selected(($filters[$key] ?? '') === 'archived')>
                                Archived
                            </option>
                        @endif
                    </select>
                </label>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            {{-- Use the paginator total, not the number on this page. --}}
            <p class="text-sm text-slate-500">
                {{ number_format($associations->total()) }} matching associations
            </p>

            <div class="flex gap-2">
                <a href="{{ route('officer.associations.index') }}"
                   class="fo-action">
                    Reset
                </a>
                <button type="submit" class="fo-primary am-button-green">
                    Apply filters
                </button>
            </div>
        </div>
    </form>

    <section class="fo-table-card">
        <header class="fo-section-heading">
            <h2 class="font-semibold">Association records</h2>
            <span class="fo-pill fo-pill-slate">
                {{ number_format($associations->total()) }} records
            </span>
        </header>

        @include('field-officer-user.associations.table')

        <x-management-pagination :records="$associations"
                                 label="Association records pagination" />
    </section>

    {{-- Reuse the existing record dialog without adding another script. --}}
    <dialog class="fo-dialog" data-record-dialog
            aria-labelledby="association-dialog-title">
        <header class="fo-dialog-header">
            <div>
                <p class="fo-eyebrow">Field Officer workspace</p>
                <h2 id="association-dialog-title" data-record-heading>
                    Association details
                </h2>
            </div>

            <button type="button" class="fo-dialog-close" data-record-close>
                Close
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     aria-hidden="true">
                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/>
                </svg>
            </button>
        </header>

        {{-- The main record and related details share one accessible native dialog. --}}
        <div class="fo-workspace">
            <div class="fo-main-panel" data-record-body aria-live="polite"></div>

            <aside class="fo-side-panel" data-record-side hidden inert
                aria-labelledby="association-panel-title">
                <header class="fo-side-header am-drawer-header">
                    <h3 id="association-panel-title">Related details</h3>
                    <button type="button" class="fo-action" data-record-side-close>
                        Close panel <span aria-hidden="true">×</span>
                    </button>
                </header>

                <div class="fo-side-body" data-record-side-body
                    aria-live="polite"></div>
            </aside>
        </div>
            </dialog>
        </div>
</x-dashboard-layout>