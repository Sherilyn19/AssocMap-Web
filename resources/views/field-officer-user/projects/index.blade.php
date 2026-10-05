<x-dashboard-layout title="Projects">
<div class="fo-coverage fo-projects space-y-6">
    {{-- Keep the existing layout title so workspace navigation still recognizes it. --}}
    <header>
        <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
        <h1>Projects and Delivery</h1>
        <p class="mt-2 text-sm text-slate-600">
            Review assigned projects, material deliveries, and association trainings.
        </p>
    </header>

    @include('shared.membership.partials.feedback')

    @if($association)
        <section class="am-drawer-intro">
            <p class="fo-eyebrow">Association context</p>
            <h2 class="fo-drawer-title">{{ $association->name }}</h2>
            <a href="{{ route('officer.projects.index') }}"
               class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-teal-800 underline">
                Show all assigned associations
            </a>
        </section>
    @endif

    {{-- Search retains the existing association context and query parameters. --}}
    <form method="GET" action="{{ route('officer.projects.index') }}"
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
                Filter projects
            </h2>
            <span class="fo-pill fo-pill-teal">Your assignments only</span>
        </div>

        <div class="flex flex-wrap items-end gap-3 px-5 py-4">
            <label class="flex min-w-0 flex-1 flex-col gap-2">
                <span class="text-sm font-semibold">Search projects</span>
                <input type="search" name="search" maxlength="255"
                       value="{{ request('search') }}"
                       placeholder="Project title"
                       class="w-full">
            </label>

            @if($association)
                <input type="hidden" name="association_id"
                       value="{{ $association->id }}">
            @endif

            <a href="{{ route('officer.projects.index', array_filter([
                'association_id' => $association?->id,
            ])) }}" class="fo-action">
                Reset
            </a>
            <button type="submit" class="fo-primary am-button-green">
                Apply filters
            </button>
        </div>
    </form>

    <section class="fo-table-card">
        <header class="fo-section-heading">
            <h2 class="font-semibold">Project records</h2>
            {{-- This total includes all matching pages, not only visible rows. --}}
            <span class="fo-pill fo-pill-slate">
                {{ number_format($projects->total()) }} matching records
            </span>
        </header>

        <div class="overflow-x-auto" tabindex="0" role="region"
             aria-label="Assigned projects">
            <table class="fo-table fo-project-table">
                <caption class="sr-only">Projects within your assignments</caption>
                <thead>
                    <tr>
                        <th scope="col">Project / ID</th>
                        <th scope="col">Association / Component</th>
                        <th scope="col">Status</th>
                        <th scope="col">Implementation</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                        @php
                            $url = route('officer.projects.show', $project);
                        @endphp
                        <tr>
                            <th scope="row">
                                <a href="{{ $url }}" data-project-open
                                   class="font-semibold hover:underline">
                                    {{ $project->title }}
                                </a>
                                <span class="fo-record-id">
                                    PROJECT-{{ str_pad((string) $project->id, 6, '0', STR_PAD_LEFT) }}
                                </span>
                            </th>

                            <td data-label="Association / Component">
                                <span class="font-medium">
                                    {{ $project->association->name }}
                                </span>
                                <small class="mt-1 block text-slate-500">
                                    {{ $project->programComponent?->name ?? 'Not recorded' }}
                                </small>
                            </td>

                            <td data-label="Status">
                                {{-- Preserve the existing status badge mapping. --}}
                                @include('shared.partials.badge', [
                                    'label' => $project->is_archived
                                        ? 'Archived'
                                        : ($project->status?->status_name ?? 'Not recorded'),
                                ])
                            </td>

                            <td data-label="Implementation">
                                {{ $project->implementation_date?->format('M d, Y') ?? 'Not recorded' }}
                            </td>

                            <td>
                                <a href="{{ $url }}" data-project-open
                                   class="fo-action"
                                   aria-label="View {{ $project->title }}">
                                    View
                                    <svg viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.5"
                                         aria-hidden="true">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              d="m9 5 7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="fo-project-empty">
                                <p class="font-semibold">No matching projects</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    Try another title or reset your search.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-management-pagination :records="$projects"
                                 label="Project records pagination" />
    </section>

    {{-- Retain the data attributes used by the existing project-modal script. --}}
    <dialog data-project-dialog class="am-project-dialog fo-project-dialog"
            aria-labelledby="project-dialog-title">
        <header class="fo-dialog-header">
            <div>
                <p class="fo-eyebrow">Field Officer workspace</p>
                <h2 id="project-dialog-title">Project and delivery details</h2>
            </div>
            <button type="button" data-project-close autofocus
                    class="fo-dialog-close">
                Close
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     aria-hidden="true">
                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/>
                </svg>
            </button>
        </header>

        <div data-project-content class="fo-project-dialog-body"
             aria-live="polite"></div>
    </dialog>
</div>
</x-dashboard-layout>