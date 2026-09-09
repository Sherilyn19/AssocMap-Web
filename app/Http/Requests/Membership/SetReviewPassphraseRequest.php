<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Provisioning is administration, not permission to approve a member application. */
final class SetReviewPassphraseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser(app(SessionUserResolver::class)->resolve($this))
            ->allows('update', $this->route('member'));
    }

    public function rules(): array
    {
        return ['review_passphrase' => [
            'required', 'string', 'min:12', 'max:72', 'confirmed',
            // Bcrypt has a 72-byte limit, including multi-byte characters.
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('The passphrase must fit within 72 bytes. Use a shorter phrase.');
                }
            },
        ]];
    }
}
