<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogIndexRequest extends FormRequest
{
    protected $redirectRoute = 'admin.audit-logs.index';

    public function authorize(): bool
    {
        return $this->attributes->get('assocmap.actor')?->role?->role_name === 'System Administrator';
    }

    public function rules(): array
    {
        return [
            'performed_by' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:255'],
            'action_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
