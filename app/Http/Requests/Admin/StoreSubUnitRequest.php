<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreSubUnitRequest
 * app/Http/Requests/Admin/StoreSubUnitRequest.php
 *
 * Validation for creating a new barangay. Barangay names must be unique
 * within the selected municipality only.
 */
class StoreSubUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_unit_id' => ['required', 'integer', 'exists:area_units,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_units', 'name')
                    ->where(fn ($query) => $query->where('area_unit_id', $this->input('area_unit_id'))),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'area_unit_id.required' => 'Please select the municipality where this barangay belongs.',
            'area_unit_id.exists'   => 'The selected municipality does not exist.',
            'name.unique'           => 'A barangay with this name already exists in the selected municipality.',
        ];
    }
}
