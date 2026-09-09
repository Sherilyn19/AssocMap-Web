<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

/** Shared profile rules keep application submission and official-member corrections consistent. */
final class MemberProfile
{
    public const ROLES = ['President', 'Secretary', 'Treasurer', 'Member'];
    public const FIELDS = ['first_name', 'middle_name', 'last_name', 'birthday', 'sex_id', 'beneficiary_type', 'contact_number', 'address'];

    public static function normalize(array $input): array
    {
        foreach (['first_name', 'middle_name', 'last_name', 'beneficiary_type', 'contact_number', 'address', 'role_in_assoc'] as $key) {
            // Do not cast malicious arrays to strings: Laravel should reject their type.
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = preg_replace('/\s+/u', ' ', trim($input[$key]));
                if ($input[$key] === '' && !in_array($key, ['first_name', 'last_name'], true)) {
                    $input[$key] = null;
                }
            }
        }
        return $input;
    }

    public static function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sex_id' => ['required', 'integer', Rule::exists('sex', 'id')],
            'beneficiary_type' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s().]{7,50}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
