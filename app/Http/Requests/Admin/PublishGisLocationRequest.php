<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

final class PublishGisLocationRequest extends GisLocationRequest
{
    public function rules(): array
    {
        // The route chooses the state. A browser flag cannot choose another action.
        return ['revision' => ['required', 'string', 'regex:/^[a-f0-9]{32}:[0-9]+$/']];
    }
}
