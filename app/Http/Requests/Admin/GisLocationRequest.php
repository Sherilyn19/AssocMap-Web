<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

abstract class GisLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware loads the current account before this request is validated.
        $actor = $this->attributes->get('assocmap.actor');
        return $actor && $actor->is_active && $actor->role?->role_name === 'System Administrator';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('location_name'))) {
            $this->merge(['location_name' => trim($this->input('location_name'))]);
        }
    }

    protected function locationRules(): array
    {
        $finite = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                $fail('Enter a finite number for '.$attribute.'.');
            }
        };
        return [
            'location_name' => ['bail', 'required', 'string', 'max:255'],
            'latitude' => ['bail', 'required', 'numeric', $finite, 'between:-90,90'],
            'longitude' => ['bail', 'required', 'numeric', $finite, 'between:-180,180'],
        ];
    }
}
