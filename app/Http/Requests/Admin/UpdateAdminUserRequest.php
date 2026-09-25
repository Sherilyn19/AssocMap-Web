<?php

namespace App\Http\Requests\Admin;

use App\Support\UserAccountId;
use Illuminate\Validation\Rule;

/**
 * app/Http/Requests/Admin/UpdateAdminUserRequest.php
 * Validation for editing an existing account. Password is nullable -
 * leaving it blank in the form keeps the current password unchanged.
 */
class UpdateAdminUserRequest extends StoreAdminUserRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = UserAccountId::parse((string) $this->route('user'));

        // Reuse creation rules while allowing this account's own email and a blank password.
        return array_replace(parent::rules(), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
    }
}
