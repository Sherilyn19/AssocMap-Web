<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

final class UpdateGisLocationRequest extends GisLocationRequest
{
    public function rules(): array
    {
        // Association, publication, and geometry are not accepted as edit fields.
        return $this->locationRules() + [
            'revision' => ['required', 'string', 'regex:/^[a-f0-9]{32}:[0-9]+$/'],
        ];
    }
}
