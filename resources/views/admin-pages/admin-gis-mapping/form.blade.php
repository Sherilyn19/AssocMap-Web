{{-- The form edits a draft. Only Save sends a request to Laravel. --}}
<section data-gis-editor hidden class="gis-editor p-4" aria-labelledby="gis-editor-title">
    <h2 id="gis-editor-title" class="text-lg font-bold">Add location</h2>
    <p class="mt-1 text-xs text-slate-600">Click the map to place the temporary pin, or enter coordinates below.</p>
    <p class="mt-1 text-xs text-slate-600">All fields are required.</p>
    <p data-gis-save-message hidden role="alert" tabindex="-1" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-slate-800"></p>
    <a data-gis-reload hidden href="{{ route('gis.index') }}" class="mt-2 inline-block text-sm text-assocmap-primary underline">Reload GIS Mapping</a>
    <form data-gis-form action="{{ route('gis.store') }}" method="POST" novalidate class="mt-4 space-y-4">
        @csrf
        <fieldset data-gis-fields class="space-y-3">
            <div>
                <label for="gis-association" class="text-xs font-semibold text-slate-700">Association</label>
                <select id="gis-association" name="association_id" required class="gis-input" aria-describedby="gis-association-error">
                    <option value="">Select an association</option>
                    @foreach($associations as $association)
                        <option value="{{ $association->id }}">{{ $association->name }}</option>
                    @endforeach
                </select>
                <p id="gis-association-error" data-gis-error="association_id" class="mt-1 text-xs text-red-700"></p>
            </div>
            <div>
                <label for="gis-location-name" class="text-xs font-semibold text-slate-700">Location name</label>
                <input id="gis-location-name" name="location_name" required maxlength="255" class="gis-input" aria-describedby="gis-name-error">
                <p id="gis-name-error" data-gis-error="location_name" class="mt-1 text-xs text-red-700"></p>
            </div>
            @foreach(['latitude' => 'Latitude (−90 to 90)', 'longitude' => 'Longitude (−180 to 180)'] as $field => $label)
                <div>
                    <label for="gis-{{ $field }}" class="text-xs font-semibold text-slate-700">{{ $label }}</label>
                    <input id="gis-{{ $field }}" name="{{ $field }}" type="text" inputmode="decimal" required class="gis-input" aria-describedby="gis-{{ $field }}-error">
                    <p id="gis-{{ $field }}-error" data-gis-error="{{ $field }}" class="mt-1 text-xs text-red-700"></p>
                </div>
            @endforeach
        </fieldset>
        <p data-gis-draft-state role="status" class="text-xs text-slate-600">Choose a position for the temporary pin.</p>
        <p data-gis-publication-note class="text-xs text-slate-600">New locations are saved as unpublished.</p>
        <div class="flex flex-wrap gap-2">
            <button data-gis-save type="submit" class="gis-button gis-save-button">Save location</button>
            <button data-gis-cancel type="button" class="gis-button">Cancel</button>
        </div>
    </form>
</section>
