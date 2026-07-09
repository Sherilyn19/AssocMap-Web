<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * app/Http/Requests/Admin/UpdateSubUnitRequest.php
 * Validation for editing an existing barangay.
 */
class UpdateSubUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subUnitId = $this->route('subUnit');

        return [
            'area_unit_id' => ['required', 'integer', 'exists:area_units,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_units', 'name')
                    ->where(function ($query) {
                        return $query->where('area_unit_id', $this->input('area_unit_id'));
                    })
                    ->ignore($subUnitId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A barangay with this name already exists in the selected municipality.',
        ];
    }
}