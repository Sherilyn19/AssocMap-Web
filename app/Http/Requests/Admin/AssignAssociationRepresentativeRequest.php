<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

final class AssignAssociationRepresentativeRequest extends AssociationInputRequest
{
    public function rules(): array
    {
        // Empty means removal; eligibility is rechecked under the association/member locks.
        return ['representative_member_id' => ['present', 'nullable', 'integer', 'min:1']];
    }
}
