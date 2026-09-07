<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already limits this module to System Administrators.
        return true;
    }

    // Existence alone does not mean a selection is eligible: the service rejects
    // archived associations and statuses outside the project-status allowlist.
    // Commodity stays free text; example commodities are not an exhaustive list.
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