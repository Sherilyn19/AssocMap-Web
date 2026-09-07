<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route middleware restricts access to System Administrators.
        return true;
    }

    // On failure, Laravel flashes this marker with old input. material-form.blade.php
    // uses it to reopen only the submitted editor. Derive it here, not from client input.
    protected function prepareForValidation(): void
    {
        $this->merge(['_material_form' => 'material-' . $this->route('material')->id]);
    }

    // A missing unit cost means unknown; zero is an explicitly recorded amount.
    // The service additionally verifies that status_id is an allowed material status.
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:100'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'delivery_date' => ['nullable', 'date'],
        ];
    }
}
