<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/** Shared normalization and draft identity for the two Area forms. */
abstract class AreaFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The route middleware rechecks the signed-in administrator.
    }

    protected function prepareForValidation(): void
    {
        $barangay = $this instanceof StoreSubUnitRequest || $this instanceof UpdateSubUnitRequest;
        $this->errorBag = $barangay ? 'barangay' : 'municipality';
// Get the record ID from the route so form input cannot select a different record.
        $this->merge([
            '_area_form' => $this->errorBag,
            '_area_id' => $this->route($barangay ? 'subUnit' : 'areaUnit'),
            'name' => is_string($this->name) ? preg_replace('/\s+/u', ' ', trim($this->name)) : $this->name,
        ]);
    }

    protected function uniqueName(string $table, ?int $id = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($table, $id): void {
            if (! is_string($value) || ($table === 'sub_units' && ! filter_var($this->input('area_unit_id'), FILTER_VALIDATE_INT))) {
                return;
            }
            // Bound parameters avoid SQL injection; the table is a code-owned constant.
            $query = DB::table($table)->when($id, fn ($q) => $q->where('id', '<>', $id))
                ->whereRaw("LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')) = LOWER(REGEXP_REPLACE(BTRIM(?), '\s+', ' ', 'g'))", [$value]);
            if ($table === 'sub_units') {
                $query->where('area_unit_id', $this->integer('area_unit_id'));
            }
            if ($query->exists()) {
                $fail($table === 'sub_units' ? 'A barangay with this name already exists in the selected municipality.' : 'A municipality with this name already exists.');
            }
        };
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }
        // FormRequest is a copy of the HTTP request. Flash its normalized, route-derived
        // draft explicitly; the global request may still contain forged recovery metadata.
        throw new HttpResponseException(redirect($this->getRedirectUrl())
            ->withInput($this->only(['name', 'address', 'area_unit_id', '_area_form', '_area_id']))
            ->withErrors($validator, $this->errorBag));
    }
}
