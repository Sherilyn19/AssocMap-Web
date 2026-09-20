<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\AssociationManagementService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssociationShowRequest extends AssociationInputRequest
{
    public function rules(): array
    {
        // Card keys are an allowlist, never client-selected table or column names.
        return [
            'related' => ['nullable', Rule::in(array_keys(AssociationManagementService::DETAIL_CARDS))],
            'related_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $this->redirect = route('admin.associations.show', $this->route('association'));
        FormRequest::failedValidation($validator);
    }
}
