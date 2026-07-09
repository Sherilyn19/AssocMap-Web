<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * app/Http/Requests/Admin/StoreAreaUnitRequest.php
 * Validation for creating a new municipality (area_units row).
 */
class StoreAreaUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255', 'unique:area_units,name'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A municipality with this name already exists.',
        ];
    }
}