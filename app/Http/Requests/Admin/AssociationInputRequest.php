<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Association;
use App\Services\SessionUserResolver;
use App\Support\AssociationFormState;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

abstract class AssociationInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser(app(SessionUserResolver::class)->resolve($this))->allows('administer', Association::class);
    }

    protected function prepareForValidation(): void
    {
        // Normalize strings only. Arrays must reach the validator as arrays and be rejected.
        foreach (['name', 'address'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/\s+/u', ' ', trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        // Relationship eligibility is checked once inside the write transaction, under locks.
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'date_joined' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'area_unit_id' => ['required', 'integer', 'min:1'],
            'sub_unit_id' => ['required', 'integer', 'min:1'],
            'program_component_id' => ['required', 'integer', 'min:1'],
            'field_officer_id' => ['required', 'integer', 'min:1'],
            'status_id' => ['required', 'integer', 'min:1'],
            'representative_member_id' => ['prohibited'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        AssociationFormState::remember($this);
        // A failed request cannot redirect to an arbitrary client-provided URL.
        $this->redirect = AssociationFormState::returnUrl($this);
        parent::failedValidation($validator);
    }
}
