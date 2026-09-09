<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use App\Support\MemberProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET parameters are untrusted too; invalid dates and arrays must not reach SQL/Blade. */
final class MemberFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Each controller applies its admin or association-scoped authorization afterward.
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:255'],
            'beneficiary_type' => ['nullable', 'string', 'max:100'],
            'role_in_assoc' => ['nullable', Rule::in(MemberProfile::ROLES)],
            'record_state' => ['nullable', Rule::in(['current', 'archived', 'all'])],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'registered_asc', 'registered_desc', 'association_asc', 'submitted_asc', 'submitted_desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
            'status' => ['nullable', Rule::in(['Pending', 'Approved', 'Rejected'])],
        ];
        foreach (['association_id', 'area_unit_id', 'sub_unit_id', 'sex_id', 'status_id', 'page', 'members_page'] as $key) {
            $rules[$key] = ['nullable', 'integer', 'min:1', 'max:2147483647'];
        }
        foreach (['registered', 'submitted'] as $prefix) {
            $rules[$prefix.'_from'] = ['nullable', 'date_format:Y-m-d'];
            $rules[$prefix.'_to'] = ['nullable', 'date_format:Y-m-d'];
            if ($this->filled($prefix.'_from')) {
                $rules[$prefix.'_to'][] = 'after_or_equal:'.$prefix.'_from';
            }
        }
        return $rules;
    }
}
