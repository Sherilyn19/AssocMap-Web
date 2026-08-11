<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProjectMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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