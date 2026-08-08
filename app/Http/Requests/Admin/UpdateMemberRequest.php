<?php

// app/Http/Requests/Admin/UpdateMemberRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateMemberRequest extends FormRequest
{
    private const ASSOCIATION_ROLES = [
        'President',
        'Secretary',
        'Treasurer',
        'Member',
    ];

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
        $this->merge([
            'first_name' => $this->normalizeText($this->input('first_name')),
            'middle_name' => $this->normalizeNullableText($this->input('middle_name')),
            'last_name' => $this->normalizeText($this->input('last_name')),
            'role_in_assoc' => $this->normalizeNullableText($this->input('role_in_assoc')),
            'beneficiary_type' => $this->normalizeNullableText($this->input('beneficiary_type')),
            'contact_number' => $this->normalizeNullableText($this->input('contact_number')),
            'address' => $this->normalizeNullableText($this->input('address')),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthday' => ['required', 'date', 'before_or_equal:today'],
            'sex_id' => ['required', 'integer', Rule::exists('sex', 'id')],
            'role_in_assoc' => ['nullable', 'string', Rule::in(self::ASSOCIATION_ROLES)],
            'beneficiary_type' => ['nullable', 'string', 'max:100'],
            'contact_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9+\-\s().]{7,50}$/',
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'date_registered' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $member = $this->route('member');

                if (!$member instanceof Member) {
                    $validator->errors()->add('member', 'The member record could not be resolved.');
                    return;
                }

                $this->validateNormalizedDuplicate($validator, $member);
            },
        ];
    }

    public function messages(): array
    {
        return [
            'birthday.before_or_equal' => 'Birthday must not be later than today.',
            'date_registered.before_or_equal' => 'Date registered must not be later than today.',
            'contact_number.regex' => 'Contact number contains unsupported characters.',
            'role_in_assoc.in' => 'Association role must be President, Secretary, Treasurer, or Member.',
        ];
    }

    private function validateNormalizedDuplicate(Validator $validator, Member $member): void
    {
        if (!$this->filled(['first_name', 'last_name', 'birthday'])) {
            return;
        }

        $firstName = $this->normalizeIdentity((string) $this->input('first_name'));
        $middleName = $this->normalizeIdentity((string) ($this->input('middle_name') ?? ''));
        $lastName = $this->normalizeIdentity((string) $this->input('last_name'));

        $duplicate = DB::table('members')
            ->where('association_id', $member->association_id)
            ->where('id', '!=', $member->id)
            ->whereDate('birthday', (string) $this->input('birthday'))
            ->whereRaw(
                "LOWER(REGEXP_REPLACE(BTRIM(first_name), '\\s+', ' ', 'g')) = ?",
                [$firstName]
            )
            ->whereRaw(
                "COALESCE(NULLIF(LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\\s+', ' ', 'g')), ''), '') = ?",
                [$middleName]
            )
            ->whereRaw(
                "LOWER(REGEXP_REPLACE(BTRIM(last_name), '\\s+', ' ', 'g')) = ?",
                [$lastName]
            )
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'first_name',
                'A member with the same name and birthday already exists in this association.'
            );
        }
    }

    private function normalizeText(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeIdentity(string $value): string
    {
        return mb_strtolower($this->normalizeText($value));
    }
}