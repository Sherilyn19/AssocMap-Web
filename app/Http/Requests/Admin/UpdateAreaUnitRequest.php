<?php

namespace App\Http\Requests\Admin;

class UpdateAreaUnitRequest extends AreaFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::in(\App\Support\MunicipalityNames::allowed((int) $this->route('areaUnit'))), $this->uniqueName('area_units', (int) $this->route('areaUnit'))],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['area_unit_id.exists' => 'Select a current municipality. Restore an archived municipality first.'];
    }
}
