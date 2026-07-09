<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * app/Http/Requests/Admin/UpdateAreaUnitRequest.php
 * Validation for editing an existing municipality.
 */
class UpdateAreaUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $areaUnitId = $this->route('areaUnit');

        return [
            'name'    => ['required', 'string', 'max:255', Rule::unique('area_units', 'name')->ignore($areaUnitId)],
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