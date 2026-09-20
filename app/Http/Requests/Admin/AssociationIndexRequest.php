<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\AssociationManagementService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssociationIndexRequest extends AssociationInputRequest
{
    public function rules(): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:255'],
            'archive_state' => ['nullable', Rule::in(['current', 'archived', 'all'])],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'date_joined_desc', 'date_joined_asc', 'created_desc', 'updated_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
            'summary' => ['nullable', Rule::in(array_keys(AssociationManagementService::REGISTER_CARDS))],
            'summary_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
        foreach (['area_unit_id', 'sub_unit_id', 'program_component_id', 'field_officer_id', 'status_id'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        // Drop invalid query parameters instead of redirecting back into an invalid-filter loop.
        $this->redirect = route('admin.associations.index');
        FormRequest::failedValidation($validator);
    }
}
