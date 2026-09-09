<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use App\Models\MemberApplication;
use App\Services\SessionUserResolver;
use App\Support\MemberProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Applicants provide personal details; the server provides ownership and Pending status. */
final class SubmitMemberApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser(app(SessionUserResolver::class)->resolve($this))
            ->allows('create', MemberApplication::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(MemberProfile::normalize($this->all()));
    }

    public function rules(): array
    {
        return MemberProfile::rules() + [
            // Explicit rejection makes attempted privilege/ownership changes visible.
            'association_id' => ['prohibited'],
            'status_id' => ['prohibited'],
            'reviewed_by_member_id' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
            'user_id' => ['prohibited'],
            'is_archived' => ['prohibited'],
        ];
    }
}
