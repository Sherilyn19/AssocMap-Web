{{--
    resources/views/admin-pages/admin-association-management/partials/form-fields.blade.php

    Reusable Association form fields.

    This partial is included by both:
    1. The Create Association modal
    2. The Edit Association modal

    Expected variables:
    - $prefix               Unique prefix such as "create" or "edit".
    - $association          Existing Association model when editing; null when creating.
    - $municipalities       Active municipality records.
    - $programComponents    Available BFAR program components.
    - $fieldOfficers        Active users with the Field Officer role.
    - $associationStatuses  Allowed association statuses: Active and Inactive.

    JavaScript integration:
    - data-field identifies each input when filling the Edit modal.
    - data-municipality identifies the municipality dropdown.
    - data-barangay identifies the dependent barangay dropdown.
--}}

@php
    /*
     * Shared Tailwind classes for inputs, selects, and textareas.
     *
     * Defining the classes once prevents repeating the same long class list
     * on every form field and keeps the design consistent.
     */
    $inputClass = '
        mt-1.5 min-h-11 w-full rounded-lg border border-slate-300
        bg-white px-3 py-2 text-sm text-slate-900
        focus:border-slate-500 focus:outline-none
        focus:ring-2 focus:ring-slate-200
    ';
@endphp

{{-- ============================================================
     SECTION 1: ASSOCIATION INFORMATION
     Contains the association name, location, program, date, and address.
============================================================ --}}
<section aria-labelledby="{{ $prefix }}-basic-heading">

    {{--
        The prefix creates unique IDs for the Create and Edit modals.
        Examples:
        - create-basic-heading
        - edit-basic-heading
    --}}
    <h3
        id="{{ $prefix }}-basic-heading"
        class="text-sm font-bold uppercase tracking-wide text-slate-700"
    >
        Association Information
    </h3>

    <div class="mt-4 grid gap-4 md:grid-cols-2">

        {{-- Association name --}}
        <label class="block md:col-span-2">
            <span class="text-sm font-medium text-slate-700">
                Association name
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            <input
                type="text"
                name="name"
                data-field="name"
                maxlength="255"
                required
                value="{{ old('name', $association?->name) }}"
                class="{{ $inputClass }}"
            >
        </label>

        {{-- Municipality --}}
        <label class="block">
            <span class="text-sm font-medium text-slate-700">
                Municipality
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            {{--
                data-municipality is used by JavaScript to update the
                barangay dropdown whenever the municipality changes.
            --}}
            <select
                name="area_unit_id"
                data-field="area_unit_id"
                data-municipality
                required
                class="{{ $inputClass }}"
            >
                <option value="">Select municipality</option>

                @foreach ($municipalities as $municipality)
                    <option
                        value="{{ $municipality->id }}"
                        @selected(
                            (string) old(
                                'area_unit_id',
                                $association?->area_unit_id
                            ) === (string) $municipality->id
                        )
                    >
                        {{ $municipality->name }}
                    </option>
                @endforeach
            </select>
        </label>

        {{-- Barangay --}}
        <label class="block">
            <span class="text-sm font-medium text-slate-700">
                Barangay
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            {{--
                Barangay options are populated by JavaScript based on the
                selected municipality.

                The server still validates that the selected barangay
                belongs to the selected municipality.
            --}}
            <select
                name="sub_unit_id"
                data-field="sub_unit_id"
                data-barangay
                required
                class="{{ $inputClass }}"
            >
                <option value="">Select municipality first</option>
            </select>
        </label>

        {{-- BFAR program component --}}
        <label class="block">
            <span class="text-sm font-medium text-slate-700">
                Program component
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            <select
                name="program_component_id"
                data-field="program_component_id"
                required
                class="{{ $inputClass }}"
            >
                <option value="">Select component</option>

                @foreach ($programComponents as $component)
                    <option
                        value="{{ $component->id }}"
                        @selected(
                            (string) old(
                                'program_component_id',
                                $association?->program_component_id
                            ) === (string) $component->id
                        )
                    >
                        {{ $component->name }}
                    </option>
                @endforeach
            </select>
        </label>

        {{-- Date the association joined the program --}}
        <label class="block">
            <span class="text-sm font-medium text-slate-700">
                Date joined
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            {{--
                The maximum date is today to prevent future dates.
                Laravel validation must also enforce this rule.
            --}}
            <input
                type="date"
                name="date_joined"
                data-field="date_joined"
                required
                max="{{ now()->format('Y-m-d') }}"
                value="{{ old(
                    'date_joined',
                    $association?->date_joined?->format('Y-m-d')
                ) }}"
                class="{{ $inputClass }}"
            >
        </label>

        {{-- Complete address --}}
        <label class="block md:col-span-2">
            <span class="text-sm font-medium text-slate-700">
                Complete address
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            <textarea
                name="address"
                data-field="address"
                rows="3"
                maxlength="500"
                required
                class="{{ $inputClass }}"
            >{{ old('address', $association?->address) }}</textarea>
        </label>
    </div>
</section>

{{-- ============================================================
     SECTION 2: ASSIGNMENT AND STATUS
     Assigns a Field Officer and sets the operational status.
============================================================ --}}
<section
    class="border-t border-slate-200 pt-5"
    aria-labelledby="{{ $prefix }}-assignment-heading"
>
    <h3
        id="{{ $prefix }}-assignment-heading"
        class="text-sm font-bold uppercase tracking-wide text-slate-700"
    >
        Assignment and Status
    </h3>

    <div class="mt-4 grid gap-4 md:grid-cols-2">

        {{-- Assigned Field Officer --}}
        <label class="block">
            <span class="text-sm font-medium text-slate-700">
                Field Officer
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            {{--
                Only active users with the Field Officer role should be
                provided by the controller or service.
            --}}
            <select
                name="field_officer_id"
                data-field="field_officer_id"
                required
                class="{{ $inputClass }}"
            >
                <option value="">Select active Field Officer</option>

                @foreach ($fieldOfficers as $officer)
                    <option
                        value="{{ $officer->id }}"
                        @selected(
                            (string) old(
                                'field_officer_id',
                                $association?->field_officer_id
                            ) === (string) $officer->id
                        )
                    >
                        {{ $officer->name }} — {{ $officer->email }}
                    </option>
                @endforeach
            </select>
        </label>

        {{-- Operational status --}}
        <label class="block">
            <span class="text-sm font-medium text-slate-700">
                Operational status
                <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            {{--
                When creating an association, Active is selected by default.

                The operational status is separate from is_archived:
                - status_id describes Active or Inactive.
                - is_archived determines whether the record is archived.
            --}}
            <select
                name="status_id"
                data-field="status_id"
                required
                class="{{ $inputClass }}"
            >
                @foreach ($associationStatuses as $status)
                    <option
                        value="{{ $status->id }}"
                        @selected(
                            (string) old(
                                'status_id',
                                $association?->status_id
                                    ?? $associationStatuses
                                        ->firstWhere('status_name', 'Active')
                                        ?->id
                            ) === (string) $status->id
                        )
                    >
                        {{ $status->status_name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    {{--
        A representative cannot normally be assigned during association
        creation because the association must first have official members.

        The representative is assigned later from the association details page.
    --}}
    <p class="mt-3 text-xs leading-5 text-slate-500">
        The representative is assigned from the association detail page after
        official members are available.
    </p>
</section>