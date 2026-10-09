<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Only the assigned Field Officer can submit a decision. */
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
            'review_passphrase' => ['prohibited'],
            'reviewed_by_user_id' => ['prohibited'],
            'decision_confirmed' => ['accepted'],
            'reviewed_by_member_id' => ['prohibited'],
            'association_id' => ['prohibited'],
            'status_id' => ['prohibited'],
        ];
    }
}
