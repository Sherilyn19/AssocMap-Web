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

        return $actor && $actor->is_active
            && in_array($actor->role?->role_name, ['System Administrator', 'Field Officer'], true);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('location_name'))) {
            $this->merge(['location_name' => trim($this->input('location_name'))]);
        }
    }

    protected function locationRules(): array
    {
        $bounded = function (string $attribute, mixed $value, \Closure $fail): void {
            // Bound numeric parsing and database expansion without rounding coordinate text.
            if (! is_scalar($value) || strlen((string) $value) > 128) {
                $fail('Use no more than 128 characters for a coordinate.');

                return;
            }
            if (preg_match('/e([+-]?\d+)$/i', trim((string) $value), $match) && abs((float) $match[1]) > 1000) {
                $fail('Use a coordinate exponent from -1000 to 1000.');
            }
        };
        $finite = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                $fail('Enter a finite number for '.$attribute.'.');
            }
        };

        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'location_name' => ['bail', 'required', 'string', 'max:255'],
            'latitude' => ['bail', 'required', $bounded, 'numeric', $finite, 'between:-90,90'],
            'longitude' => ['bail', 'required', $bounded, 'numeric', $finite, 'between:-180,180'],
        ];
    }
}
