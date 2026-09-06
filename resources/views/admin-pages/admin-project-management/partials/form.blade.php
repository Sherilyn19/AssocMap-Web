<!-- resources/views/admin-pages/admin-project-management/partials/form.blade.php -->
@php
    $currentProject = $project;
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6 rounded-2xl border border-assocmap-border bg-white p-6 shadow-card">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="md:col-span-2">
            <label for="title" class="mb-1 block text-sm font-semibold text-assocmap-text">Project Title</label>
            <input id="title" name="title" value="{{ old('title', $currentProject?->title) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        <div>
            <label for="association_id" class="mb-1 block text-sm font-semibold text-assocmap-text">Association</label>
            <select id="association_id" name="association_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Select association</option>
                @foreach ($associations as $association)
                    <option value="{{ $association->id }}"
                        @selected((string) old('association_id', $currentProject?->association_id) === (string) $association->id)>
                        {{ $association->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="program_component_id" class="mb-1 block text-sm font-semibold text-assocmap-text">Program Component</label>
            <select id="program_component_id" name="program_component_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Select component</option>
                @foreach ($programComponents as $component)
                    <option value="{{ $component->id }}"
                        @selected((string) old('program_component_id', $currentProject?->program_component_id) === (string) $component->id)>
                        {{ $component->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Aquaculture, Capture Fisheries, or Post-Harvest are classifications.</p>
        </div>

        <div>
            <label for="commodity_type" class="mb-1 block text-sm font-semibold text-assocmap-text">Commodity Type</label>
            <input id="commodity_type" name="commodity_type" value="{{ old('commodity_type', $currentProject?->commodity_type) }}" required
                   placeholder="e.g. Bangus, Tilapia, Seaweed"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <p class="mt-1 text-xs text-slate-500">Use the actual fisheries/livelihood commodity, not the program component.</p>
        </div>

        <div>
            <label for="implementation_date" class="mb-1 block text-sm font-semibold text-assocmap-text">Implementation Date</label>
            <input id="implementation_date" type="date" name="implementation_date"
                   value="{{ old('implementation_date', $currentProject?->implementation_date?->format('Y-m-d')) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        <div>
            <label for="budget" class="mb-1 block text-sm font-semibold text-assocmap-text">Budget</label>
            <input id="budget" type="number" min="0" step="0.01" name="budget"
                   value="{{ old('budget', $currentProject?->budget) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        <div>
            <label for="status_id" class="mb-1 block text-sm font-semibold text-assocmap-text">Project Status</label>
            <select id="status_id" name="status_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Select status</option>
                @foreach ($projectStatuses as $status)
                    <option value="{{ $status->id }}"
                        @selected((string) old('status_id', $currentProject?->status_id) === (string) $status->id)>
                        {{ $status->status_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="remarks" class="mb-1 block text-sm font-semibold text-assocmap-text">Remarks</label>
            <textarea id="remarks" name="remarks" rows="4"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('remarks', $currentProject?->remarks) }}</textarea>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-5">
        <a href="{{ $currentProject ? route('projects.show', $currentProject) : route('projects.index') }}"
           class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Cancel
        </a>
        <button type="submit"
                class="rounded-lg bg-assocmap-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
            {{ $submitLabel }}
        </button>
    </div>
</form>
