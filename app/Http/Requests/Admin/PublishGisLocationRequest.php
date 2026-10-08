<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

final class PublishGisLocationRequest extends GisLocationRequest
{
    public function rules(): array
    {
        // The route chooses the operation; the user must explicitly confirm it.
        return [
            'revision' => [
                'required',
                'string',
                'regex:/^[a-f0-9]{32}:[0-9]+$/',
            ],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.required' => 'Confirm this GIS action before continuing.',
            'confirmed.accepted' => 'Confirm this GIS action before continuing.',
        ];
    }
}