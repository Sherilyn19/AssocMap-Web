<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route middleware checks the administrator's current database role.
        return true;
    }

    public function rules(): array
    {
        return [
            'association_id' => ['required', 'integer', 'exists:associations,id'],
            'title' => ['required', 'string', 'max:255'],
            'program_component_id' => ['required', 'integer', 'exists:program_components,id'],
            'training_type' => ['required', 'string', 'max:100'],
            'venue' => ['required', 'string', 'max:255'],
            'date_conducted' => ['required', 'date_format:Y-m-d'],
            'training_cost' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'conducted_by' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
