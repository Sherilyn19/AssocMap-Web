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

@if(request()->ajax())
    @include('field-officer-user.projects.editor-fields')
@else
    <x-dashboard-layout :title="$heading">
        @include('field-officer-user.projects.editor-fields')
    </x-dashboard-layout>
@endif
