<!-- resources/views/admin-pages/admin-project-management/index.blade.php -->
<x-dashboard-layout title="Project Management">
    {{-- Admin-only Project Management index. --}}
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-assocmap-primary">System Administrator</p>
                <h1 class="mt-1 text-2xl font-bold text-assocmap-text">Project Management</h1>
                <p class="mt-1 text-sm text-assocmap-secondary">
                    Manage projects, classifications, budgets, materials, delivery information, and project archives.
                </p>
            </div>

            <a href="{{ route('projects.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-assocmap-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                + Create Project
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-2xl border border-assocmap-border bg-white p-5 shadow-card">
            <form method="GET" action="{{ route('projects.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="search" class="mb-1 block text-xs font-semibold text-assocmap-text">Search</label>
                    <input id="search" name="search" value="{{ request('search') }}"
                           placeholder="Project, commodity, or association"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label for="status_id" class="mb-1 block text-xs font-semibold text-assocmap-text">Status</label>
                    <select id="status_id" name="status_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($projectStatuses as $status)
                            <option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="program_component_id" class="mb-1 block text-xs font-semibold text-assocmap-text">Program Component</label>
                    <select id="program_component_id" name="program_component_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All components</option>
                        @foreach ($programComponents as $component)
                            <option value="{{ $component->id }}" @selected((string) request('program_component_id') === (string) $component->id)>
                                {{ $component->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2 md:col-span-4">
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Apply Filters
                    </button>
                    <a href="{{ route('projects.index') }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                    <a href="{{ route('projects.index', ['archived' => $includeArchived ? null : 1]) }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        {{ $includeArchived ? 'Hide Archived' : 'Show Archived' }}
                    </a>
                </div>
            </form>
        </div>

        @forelse ($projects as $project)
            <article class="rounded-2xl border border-assocmap-border bg-white p-5 shadow-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-assocmap-text">{{ $project->title }}</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                {{ $project->status?->status_name ?? 'Unknown' }}
                            </span>
                            @if ($project->is_archived)
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Archived</span>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-assocmap-secondary">
                            {{ $project->association?->name ?? 'Unknown association' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('projects.show', $project) }}"
                           class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            View
                        </a>
                        @unless ($project->is_archived)
                            <a href="{{ route('projects.edit', $project) }}"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('projects.archive', $project) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        onclick="return confirm('Archive this project? It will not be permanently deleted.')"
                                        class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                                    Archive
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <dt class="text-xs text-assocmap-secondary">Program Component</dt>
                        <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->programComponent?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-assocmap-secondary">Commodity Type</dt>
                        <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->commodity_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-assocmap-secondary">Implementation Date</dt>
                        <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->implementation_date?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-assocmap-secondary">Budget</dt>
                        <dd class="mt-1 text-sm font-semibold text-assocmap-text">₱{{ number_format((float) $project->budget, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-assocmap-secondary">Materials</dt>
                        <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->materials_count }}</dd>
                    </div>
                </dl>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="font-semibold text-assocmap-text">No projects found.</p>
                <p class="mt-1 text-sm text-assocmap-secondary">Adjust the filters or create a new project.</p>
            </div>
        @endforelse

        <div>
            {{ $projects->links() }}
        </div>
    </div>
</x-dashboard-layout>