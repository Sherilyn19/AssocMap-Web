<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A shared login alone is not review authority; the service verifies the private secret. */
final class ReviewMemberApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser(app(SessionUserResolver::class)->resolve($this))
            ->allows('review', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['Approved', 'Rejected'])],
            'rejection_reason' => ['required_if:decision,Rejected', 'nullable', 'string', 'max:2000'],
            'review_passphrase' => ['required', 'string', 'max:72'],
            'reviewed_by_member_id' => ['prohibited'],
            'association_id' => ['prohibited'],
            'status_id' => ['prohibited'],
        ];
    }
}
