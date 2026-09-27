<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

final class StoreGisLocationRequest extends GisLocationRequest
{
    public function rules(): array
    {
        return $this->locationRules() + [
            'submission_token' => ['required', 'uuid'],
            'association_id' => ['bail', 'required', 'integer', Rule::exists('associations', 'id')->where(fn ($query) => $query->where('is_archived', false))],
        ];
    }

    public function messages(): array
    {
        return ['association_id.exists' => 'Select an association that is not archived.'];
    }
}
