<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['municipalities', 'barangays'])],
            'search' => ['nullable', 'string', 'max:255'],
            'brgy_search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'brgy_status' => ['nullable', Rule::in(['active', 'archived'])],
            'area_unit_id' => ['nullable', 'integer', 'min:1'],
            'muni_sort' => ['nullable', Rule::in(['name', 'created_at', 'updated_at'])],
            'brgy_sort' => ['nullable', Rule::in(['name', 'created_at', 'updated_at'])],
            'muni_page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'brgy_page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['nullable', Rule::in([12, 24, 48])],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('areas.index');
    }
}
