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
        $this->merge(\App\Support\MemberProfile::normalize($this->all()));
        // Use the bound record, never a client-selected hidden ID, to recover failed edits.
        $this->session()->flash('edit_member_id', $this->route('member')?->id);
    }

    public function rules(): array
    {
        return \App\Support\MemberProfile::rules() + [
            'role_in_assoc' => ['nullable', 'string', Rule::in(\App\Support\MemberProfile::ROLES)],
            'date_registered' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:birthday'],
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
        // Never send an invalid date or array to a PostgreSQL identity query.
        if ($validator->errors()->isNotEmpty() || !$this->filled(['first_name', 'last_name', 'birthday'])) {
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
