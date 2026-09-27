<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

// Historical dates are excluded even when manually submitted.
final class UpdateAssociationRequest extends AssociationInputRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['date_joined' => ['exclude']]);
    }
}
