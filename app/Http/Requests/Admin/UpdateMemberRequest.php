<?php

// app/Http/Requests/Admin/UpdateMemberRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        if (!$member instanceof Member) {
            return false;
        }

        $user = app(SessionUserResolver::class)->resolve($this);

        return Gate::forUser($user)->allows('update', $member);
    }

    protected function prepareForValidation(): void
    {
        // Normalize strings only; malformed arrays remain arrays so validation rejects them.
        $this->merge(\App\Support\MemberProfile::normalize($this->only('contact_number')));
        // Use the bound record, never a client-selected hidden ID, to recover failed edits.
        $this->session()->flash('edit_member_id', $this->route('member')?->id);
    }

    public function rules(): array
    {
        // Only this validated field can reach the normal member update service.
        return ['contact_number' => ['present', ...\App\Support\MemberProfile::rules()['contact_number']]];
    }
}
