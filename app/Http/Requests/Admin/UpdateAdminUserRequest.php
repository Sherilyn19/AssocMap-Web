<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * app/Http/Requests/Admin/UpdateAdminUserRequest.php
 * Validation for editing an existing account. Password is nullable -
 * leaving it blank in the form keeps the current password unchanged.
 */
class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}