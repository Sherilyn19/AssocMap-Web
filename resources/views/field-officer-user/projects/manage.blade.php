@php
    $isProject = $kind === 'project';
    $record = $isProject ? $project : $material;
    $editing = $record !== null;

    $heading = $isProject
        ? ($editing ? 'Edit project' : 'Create project')
        : ($editing ? 'Edit material' : 'Add material');

    $action = $isProject
        ? ($editing
            ? route('officer.projects.update', $project)
            : route('officer.projects.store'))
        : ($editing
            ? route('officer.projects.materials.update', [$project, $material])
            : route('officer.projects.materials.store', $project));

    // Safely restore values after normal form validation.
    $value = function (string $field) use ($record): string {
        $stored = $record?->{$field};

        if ($stored instanceof \DateTimeInterface) {
            $stored = $stored->format('Y-m-d');
        }

        $result = old($field, $stored);
        return is_scalar($result) ? (string) $result : '';
    };
@endphp

<x-dashboard-layout :title="$heading">
    {{-- JavaScript extracts only this section into the management dialog. --}}
    <section data-project-editor class="fo-coverage space-y-5">
        <header class="am-drawer-intro">
            <p class="fo-eyebrow">Field Officer workspace</p>
            <h2 class="fo-drawer-title">{{ $heading }}</h2>
            @if($project)
                <p class="text-sm text-slate-600">{{ $project->title }}</p>
            @endif
        </header>

        @if($errors->any())
            <div class="fo-warning" role="alert">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST"
              action="{{ $action }}"
              data-project-write
              class="fo-card space-y-4 p-5">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            @if($isProject)
                @if(!$editing)
                    <label class="block">
                        <span class="block font-semibold">Assigned association</span>
                        <select name="association_id" required class="mt-2 w-full">
                            <option value="">Select association</option>
                            @foreach($options['associations'] as $association)
                                <option value="{{ $association->id }}"
                                        @selected($value('association_id') === (string) $association->id)>
                                    {{ $association->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @else
                    {{-- Do not submit an association ID when editing. --}}
                    <div>
                        <p class="text-sm text-slate-500">Association</p>
                        <p class="font-semibold">{{ $project->association->name }}</p>
                    </div>
                @endif
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($isProject
                    ? [
                        ['title', 'Project title', 'text', true],
                        ['commodity_type', 'Commodity', 'text', true],
                        ['implementation_date', 'Implementation date', 'date', true],
                        ['budget', 'Project budget (PHP, optional)', 'number', false],
                        ['terminated_on', 'Termination date', 'date', false],
                    ]
                    : [
                        ['item_name', 'Material name', 'text', true],
                        ['quantity', 'Quantity', 'number', true],
                        ['unit', 'Unit', 'text', true],
                        ['unit_cost', 'Unit cost (PHP)', 'number', false],
                        ['delivery_date', 'Delivery date', 'date', false],
                    ]
                    as [$field, $label, $type, $required])
                    <label class="block">
                        <span class="block font-semibold">{{ $label }}</span>
                        <input name="{{ $field }}"
                               type="{{ $type }}"
                               value="{{ $value($field) }}"
                               @required($required)
                               @if($type === 'number')
                                   step="0.01"
                                   min="{{ $field === 'quantity' ? '0.01' : '0' }}"
                               @endif
                               @if($type === 'text')
                                   maxlength="{{ $field === 'unit' ? 100 : 255 }}"
                               @endif
                               class="mt-2 w-full">
                    </label>
                @endforeach

                @if($isProject)
                    <label class="block">
                        <span class="block font-semibold">Program component</span>
                        <select name="program_component_id" required class="mt-2 w-full">
                            <option value="">Select component</option>
                            @foreach($options['programComponents'] as $component)
                                <option value="{{ $component->id }}"
                                        @selected($value('program_component_id') === (string) $component->id)>
                                    {{ $component->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="block">
                    <span class="block font-semibold">Status</span>
                    <select name="status_id" required class="mt-2 w-full">
                        <option value="">Select status</option>
                        @foreach($isProject ? $options['projectStatuses'] : $options['materialStatuses'] as $status)
                            <option value="{{ $status->id }}"
                                    @selected($value('status_id') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if($isProject)
                <label class="block">
                    <span class="block font-semibold">Remarks</span>
                    <textarea name="remarks"
                              maxlength="5000"
                              rows="3"
                              class="mt-2 w-full">{{ $value('remarks') }}</textarea>
                </label>
            @endif

            <button type="submit" class="fo-action am-button-green">
                Save {{ $isProject ? 'project' : 'material' }}
            </button>
        </form>

        @if($editing)
            {{-- Archiving retains the record and its existing relationships. --}}
            <details class="fo-card p-5">
                <summary class="cursor-pointer font-semibold">
                    Archive {{ $isProject ? 'project' : 'material' }}
                </summary>

                <form method="POST"
                      action="{{ $isProject
                          ? route('officer.projects.archive', $project)
                          : route('officer.projects.materials.archive', [$project, $material]) }}"
                      data-project-write
                      data-project-archive
                      class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')

                    <p class="text-sm text-slate-600">
                        This record will remain available for history and cannot be edited.
                    </p>

                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="confirm" value="1" required>
                        <span>I confirm that this record should be archived.</span>
                    </label>

                    <button type="submit" class="fo-action am-button-warning">
                        Confirm archive
                    </button>
                </form>
            </details>
        @endif
    </section>
</x-dashboard-layout>