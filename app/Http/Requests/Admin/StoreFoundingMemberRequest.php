<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\SessionUserResolver;
use App\Support\MemberProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreFoundingMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser(app(SessionUserResolver::class)->resolve($this))
            ->allows('update', $this->route('association'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(MemberProfile::normalize($this->all()));
    }

    public function rules(): array
    {
        return MemberProfile::rules() + [
            'justification' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'profile_verified' => ['accepted'],
            'association_id' => ['prohibited'], 'application_id' => ['prohibited'],
            'user_id' => ['prohibited'], 'role_in_assoc' => ['prohibited'],
            'date_registered' => ['prohibited'], 'is_archived' => ['prohibited'],
            'review_passphrase_hash' => ['prohibited'], 'representative_member_id' => ['prohibited'],
        ];
    }
}
