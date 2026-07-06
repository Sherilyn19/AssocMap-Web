<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * app/Http/Requests/Admin/StoreAdminUserRequest.php
 * Validation for creating a new system account.
 * Route access is already restricted to System Administrator by the
 * assocmap.auth middleware, so authorize() just returns true here.
 */
class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}