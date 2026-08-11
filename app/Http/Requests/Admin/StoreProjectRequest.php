<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already limits this module to System Administrators.
        return true;
    }

    public function rules(): array
    {
        return [
            'association_id' => ['required', 'integer', 'exists:associations,id'],
            'title' => ['required', 'string', 'max:255'],
            'commodity_type' => ['required', 'string', 'max:255'],
            'program_component_id' => ['required', 'integer', 'exists:program_components,id'],
            'implementation_date' => ['required', 'date'],
            'budget' => ['required', 'numeric', 'min:0'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}