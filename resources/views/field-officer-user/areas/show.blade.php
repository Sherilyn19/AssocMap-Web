@php
    $memberAreaView = request()->routeIs('member.areas.*');
    $areaRoutes = $memberAreaView ? 'member.areas' : 'officer.areas';
    $areaPageTitle = $memberAreaView ? 'Association Area Details' : 'Assigned Area Details';
@endphp

<x-dashboard-layout :title="$areaPageTitle">
<div class="am-area-page space-y-5">
    <a href="{{ route($areaRoutes.'.index') }}"
       class="am-user-button am-user-button-secondary">
        {{ $memberAreaView ? 'Back to View Area Records' : 'Back to Assigned Areas' }}
    </a>

    {{-- This section is also loaded into the coverage dialog. --}}
    <section data-area-detail-content class="fo-area-detail space-y-5">
        <header class="fo-area-heading">
            <div class="fo-area-icon" aria-hidden="true">
                {{-- Heroicons outline: map pin. --}}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                </svg>
            </div>

            <div class="min-w-0">
                <p class="fo-eyebrow">City / Municipality coverage</p>
                <h2 class="mt-1 text-2xl font-bold">{{ $area->name }}</h2>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="fo-pill fo-pill-slate" data-tone="info">
                        {{ number_format($associations->total()) }}
                        {{ $associations->total() === 1 ? 'association' : 'associations' }}
                    </span>
                    <span class="fo-pill fo-pill-slate">{{ $memberAreaView ? 'Your association only' : 'All assigned records' }}</span>
                    <span class="fo-pill fo-pill-slate"
                          data-tone="{{ $area->is_archived ? 'warning' : 'success' }}">
                        {{ $area->is_archived ? 'Area record archived' : 'Area record not archived' }}
                    </span>
                </div>
            </div>
        </header>

        <div class="fo-table-card overflow-x-auto" tabindex="0"
             role="region" aria-label="Associations available to your account">
            <table class="fo-table">
                <caption class="sr-only">
                    Associations available to your account in {{ $area->name }}
                </caption>
                <thead>
                    <tr>
                        @foreach(['No.', 'Association', 'Barangay / Program',
                            'Status', 'Members', 'Projects', 'GIS', 'Details'] as $heading)
                            <th scope="col">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse($associations as $association)
                        @php
                            $details = fn ($section) => route($areaRoutes.'.details', [
                                'areaUnit' => $area->id,
                                'association' => $association->id,
                                'section' => $section,
                            ]);

                            $status = $association->is_archived
                                ? 'Archived'
                                : ($association->status?->status_name ?? 'Not recorded');

                            $tone = $association->is_archived
                                ? 'warning'
                                : ($status === 'Active' ? 'success' : 'neutral');

                            $statusHelp = $association->is_archived
                                ? 'Archived association. Retained for history; operational changes are restricted.'
                                : 'Recorded operational status: '.$status.'. Archive state is maintained separately.';
                        @endphp

                        <tr>
                            <td class="text-slate-500">
                                {{ $associations->firstItem() + $loop->index }}
                            </td>
                            <th scope="row">
                                <a data-area-drawer
                                   href="{{ $details('association') }}"
                                   class="font-semibold hover:underline">
                                    {{ $association->name }}
                                </a>
                                <span class="fo-record-id">
                                    ASSOC-{{ str_pad((string) $association->id, 6, '0', STR_PAD_LEFT) }}
                                </span>
                            </th>
                            <td>
                                <span class="font-medium">
                                    {{ $association->subUnit?->name ?? 'Not recorded' }}
                                </span>
                                <small class="mt-1 block text-slate-500">
                                    {{ $association->programComponent?->name ?? 'Program not recorded' }}
                                </small>

                                @if(!$association->subUnit ||
                                    (int) $association->subUnit->area_unit_id !== (int) $area->id)
                                    <span class="fo-pill fo-pill-slate mt-2" data-tone="warning">
                                        Area information needs review
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{-- Native disclosure also works by keyboard and touch. --}}
                                <details class="fo-status">
                                    <summary class="fo-pill fo-pill-slate"
                                             data-tone="{{ $tone }}">
                                        {{ $status }}
                                        <span aria-hidden="true">ⓘ</span>
                                    </summary>
                                    <p class="fo-status-help">{{ $statusHelp }}</p>
                                </details>
                            </td>

                            @foreach([
                                'members' => ['current_members_count', 'Non-archived members'],
                                'projects' => ['retained_projects_count', 'Non-archived projects'],
                                'gis' => [
                                    'mapped_locations_count',
                                    $memberAreaView ? 'Published locations' : 'Saved locations',
                                ],
                            ] as $section => [$countField, $label])
                                <td>
                                    <a data-area-drawer
                                       href="{{ $details($section) }}"
                                       class="fo-count fo-count-teal"
                                       aria-label="{{ $label }} for {{ $association->name }}: {{ $association->$countField }}">
                                        {{ number_format($association->$countField) }}
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                </td>
                            @endforeach

                            <td>
                                <a data-area-drawer
                                   href="{{ $details('association') }}"
                                   class="fo-action">
                                    View
                                    <svg aria-hidden="true" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="m9 5 7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-slate-500">
                                No associations are available to your account in this municipality.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div data-area-pagination>
            <x-management-pagination :records="$associations"
                                     label="Association records pagination"/>
        </div>
    </section>
</div>
</x-dashboard-layout>
