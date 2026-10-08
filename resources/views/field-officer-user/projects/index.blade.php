<x-dashboard-layout title="Projects">
<div class="fo-coverage fo-projects space-y-6" data-officer-projects>
    {{-- Keep the existing layout title so workspace navigation still recognizes it. --}}
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
        <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
        <h1 class="mt-3 text-3xl font-bold text-slate-900">Projects and Delivery</h1>
        <p class="mt-2 text-sm text-slate-600">
            Review assigned projects, material deliveries, and association trainings.
        </p>
        </div>

                {{-- Keep both header actions together with consistent spacing. --}}
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('gis.officer.index') }}"
               class="fo-action fo-project-map">
                {{-- Map icon uses the button's text color. --}}
                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.5"
                     aria-hidden="true">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="m9 6 6-3 6 3v15l-6-3-6 3-6-3V3l6 3Zm0 0v15m6-18v15"/>
                </svg>
                Open GIS Mapping
            </a>

            {{-- Open the project form in the existing editor dialog. --}}
            <a href="{{ route('officer.projects.create') }}"
               data-project-manage
               data-editor-title="Create project"
               data-editor-create
               class="fo-action am-button-green">
                <span aria-hidden="true">＋</span>
                Create project
            </a>
        </div>
        
    </header>

    @include('shared.membership.partials.feedback')

    {{-- Count database records across the assignment, not only the visible page. --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Overall assigned projects">
        @foreach([
            'total' => ['Assigned projects', 'Includes archived projects.'],
            'current' => ['Current projects', 'Projects not marked archived.'],
            'associations' => ['Associations with projects', 'Within your current assignments.'],
            'missing' => ['Delivery dates missing', 'Projects with undated material records.'],
        ] as $key => [$label, $description])
            <div class="fo-summary-card {{ $key === 'missing' ? 'fo-summary-warning' : '' }}">
                <p class="text-sm font-medium uppercase text-slate-600">{{ $label }}</p>
                <p data-count-up class="mt-3 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($summary[$key]) }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $description }}</p>
            </div>
        @endforeach
    </section>

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

        <div class="grid gap-4 px-5 py-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Search projects</span>
                <input type="search" name="search" maxlength="255"
                       value="{{ request('search') }}"
                       placeholder="Project title"
                       class="w-full">
            </label>

            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Association</span>
                <select name="association_id">
                    <option value="">All assigned associations</option>
                    @foreach($associationOptions as $option)
                        <option value="{{ $option->id }}" @selected((string) request('association_id') === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Project record</span>
                <select name="archive">
                    @foreach(['all' => 'All records', 'current' => 'Current projects', 'archived' => 'Archived projects'] as $value => $label)
                        <option value="{{ $value }}" @selected((request('archive') ?: 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Delivery dates</span>
                <select name="delivery">
                    @foreach(['all' => 'All records', 'missing' => 'Missing dates', 'recorded' => 'All dates recorded', 'none' => 'No materials recorded'] as $value => $label)
                        <option value="{{ $value }}" @selected((request('delivery') ?: 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
            <p class="text-sm text-slate-500">{{ number_format($projects->total()) }} matching projects</p>
            <div class="flex gap-2">
            <a href="{{ route('officer.projects.index') }}" class="fo-action">
                Reset
            </a>
            <button type="submit" class="fo-primary am-button-green">
                Apply filters
            </button>
            </div>
        </div>
        @if($errors->any())
            <div class="fo-warning m-5" role="alert">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
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
                        <th scope="col">No.</th>
                        <th scope="col">Project / ID</th>
                        <th scope="col">Association / Component</th>
                        <th scope="col">Status</th>
                        <th scope="col">Implementation</th>
                        <th scope="col">Delivery dates</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                        @php
                            $url = route('officer.projects.show', $project);
                        @endphp
                        <tr>
                            <td data-label="No." class="text-slate-500">{{ $projects->firstItem() + $loop->index }}</td>
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

                            <td data-label="Delivery dates">
                                <a href="{{ $url }}" data-project-open class="fo-pill {{ $project->materials_count > $project->recorded_deliveries_count ? 'fo-pill-amber' : 'fo-pill-teal' }}"
                                   aria-label="View delivery records for {{ $project->title }}">
                                    {{ $project->recorded_deliveries_count }} / {{ $project->materials_count }} recorded
                                </a>
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
                            <td colspan="7" class="fo-project-empty">
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
