<x-dashboard-layout title="Project Details">
    {{-- Project details is the main hub for project-level material management. --}}
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('projects.index') }}" class="text-sm font-semibold text-assocmap-primary hover:underline">&larr; Back to Project Management</a>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-assocmap-text">{{ $project->title }}</h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        {{ $project->status?->status_name ?? 'Unknown' }}
                    </span>
                    @if ($project->is_archived)
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Archived</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-assocmap-secondary">{{ $project->association?->name }}</p>
            </div>

            @unless ($project->is_archived)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('projects.edit', $project) }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Edit Project
                    </a>
                    <form method="POST" action="{{ route('projects.archive', $project) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                onclick="return confirm('Archive this project? It will not be permanently deleted.')"
                                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Archive
                        </button>
                    </form>
                </div>
            @endunless
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <section class="rounded-2xl border border-assocmap-border bg-white p-6 shadow-card">
            <h2 class="text-lg font-bold text-assocmap-text">Project Information</h2>
            <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs text-assocmap-secondary">Association</dt>
                    <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->association?->name ?? 'â€”' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-assocmap-secondary">Program Component</dt>
                    <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->programComponent?->name ?? 'â€”' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-assocmap-secondary">Commodity Type</dt>
                    <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->commodity_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-assocmap-secondary">Implementation Date</dt>
                    <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->implementation_date?->format('M d, Y') ?? 'â€”' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-assocmap-secondary">Budget</dt>
                    <dd class="mt-1 text-sm font-semibold text-assocmap-text">â‚±{{ number_format((float) $project->budget, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-assocmap-secondary">Remarks</dt>
                    <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $project->remarks ?: 'â€”' }}</dd>
                </div>
            </dl>
        </section>

        @if (! $project->is_archived)
            <section class="rounded-2xl border border-assocmap-border bg-white p-6 shadow-card">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-assocmap-text">Add Project Material</h2>
                        <p class="text-sm text-assocmap-secondary">Materials belong to this project and cannot be assigned across projects.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('projects.materials.store', $project) }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-6">
                    @csrf

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-assocmap-text">Item Name</label>
                        <input name="item_name" value="{{ old('item_name') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-assocmap-text">Quantity</label>
                        <input type="number" min="0.01" step="0.01" name="quantity" value="{{ old('quantity') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-assocmap-text">Unit</label>
                        <input name="unit" value="{{ old('unit') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-assocmap-text">Unit Cost</label>
                        <input type="number" min="0" step="0.01" name="unit_cost" value="{{ old('unit_cost') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-assocmap-text">Status</label>
                        <select name="status_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($materialStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->status_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-assocmap-text">Delivery Date</label>
                        <input type="date" name="delivery_date" value="{{ old('delivery_date') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="md:col-span-6">
                        <button class="rounded-lg bg-assocmap-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                            Add Material
                        </button>
                    </div>
                </form>
            </section>
        @endif

        <section class="rounded-2xl border border-assocmap-border bg-white p-6 shadow-card">
            <div>
                <h2 class="text-lg font-bold text-assocmap-text">Project Materials</h2>
                <p class="text-sm text-assocmap-secondary">Cost is derived as quantity Ã— unit cost; no duplicated total-cost field is stored.</p>
            </div>

            <div class="mt-5 space-y-4">
                @forelse ($project->materials as $material)
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="font-semibold text-assocmap-text">{{ $material->item_name }}</h3>
                                <p class="mt-1 text-sm text-assocmap-secondary">
                                    {{ $material->quantity }} {{ $material->unit }}
                                    @if ($material->unit_cost !== null)
                                        Â· â‚±{{ number_format((float) $material->unit_cost, 2) }} / {{ $material->unit }}
                                    @endif
                                </p>
                            </div>

                            <span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                {{ $material->status?->status_name ?? 'Unknown' }}
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-assocmap-secondary">Delivery Date</dt>
                                <dd class="mt-1 text-sm font-semibold text-assocmap-text">{{ $material->delivery_date?->format('M d, Y') ?? 'â€”' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-assocmap-secondary">Calculated Cost</dt>
                                <dd class="mt-1 text-sm font-semibold text-assocmap-text">
                                    â‚±{{ number_format($material->total_cost, 2) }}
                                </dd>
                            </div>
                            <div class="sm:text-right">
                                @unless ($project->is_archived)
                                    <details class="sm:inline-block text-left">
                                        <summary class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">
                                            Edit Material
                                        </summary>

                                        <form method="POST" action="{{ route('projects.materials.update', [$project, $material]) }}" class="mt-3 rounded-lg bg-slate-50 p-4">
                                            @csrf
                                            @method('PUT')

                                            <div class="grid grid-cols-1 gap-3">
                                                <input name="item_name" value="{{ $material->item_name }}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                <input type="number" min="0.01" step="0.01" name="quantity" value="{{ $material->quantity }}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                <input name="unit" value="{{ $material->unit }}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                <input type="number" min="0" step="0.01" name="unit_cost" value="{{ $material->unit_cost }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                <select name="status_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                    @foreach ($materialStatuses as $status)
                                                        <option value="{{ $status->id }}" @selected($material->status_id === $status->id)>{{ $status->status_name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="date" name="delivery_date" value="{{ $material->delivery_date?->format('Y-m-d') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                <button class="rounded-lg bg-assocmap-primary px-3 py-2 text-xs font-semibold text-white">Save Material</button>
                                            </div>
                                        </form>
                                    </details>
                                @endunless
                            </div>
                        </dl>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center">
                        <p class="font-semibold text-assocmap-text">No materials recorded.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-dashboard-layout>