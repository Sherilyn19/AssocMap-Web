<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreSubUnitRequest extends AreaFormRequest
{
    public function rules(): array
    {
        return [
            'area_unit_id' => ['bail', 'required', 'integer', Rule::exists('area_units', 'id')->where(fn ($query) => $query->where('is_archived', false))],
            'name' => ['bail', 'required', 'string', 'max:255', $this->uniqueName('sub_units')],
        ];
    }

    public function messages(): array
    {
        return ['area_unit_id.exists' => 'Select a current municipality. Restore an archived municipality first.'];
    }
}
