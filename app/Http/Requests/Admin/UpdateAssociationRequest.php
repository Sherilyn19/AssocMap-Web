<?php

// app/Http/Requests/Admin/UpdateAssociationRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Association;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAssociationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            'address' => preg_replace('/\s+/', ' ', trim((string) $this->input('address'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'area_unit_id' => [
                'required',
                'integer',
                Rule::exists('area_units', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'sub_unit_id' => [
                'required',
                'integer',
                Rule::exists('sub_units', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'program_component_id' => ['required', 'integer', Rule::exists('program_components', 'id')],
            'field_officer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'representative_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')],
            'address' => ['required', 'string', 'max:500'],
            'date_joined' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $association = $this->route('association');

                if (!$association instanceof Association) {
                    $validator->errors()->add('association', 'The association record could not be resolved.');
                    return;
                }

                $this->validateBarangayMunicipalityPair($validator);
                $this->validateFieldOfficer($validator);
                $this->validateOperationalStatus($validator);
                $this->validateRepresentative($validator, $association);
                $this->validateNormalizedDuplicate($validator, $association);
            },
        ];
    }

    private function validateBarangayMunicipalityPair(Validator $validator): void
    {
        if (!$this->filled(['area_unit_id', 'sub_unit_id'])) {
            return;
        }

        $valid = DB::table('sub_units')
            ->where('id', $this->integer('sub_unit_id'))
            ->where('area_unit_id', $this->integer('area_unit_id'))
            ->where('is_archived', false)
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'sub_unit_id',
                'The selected barangay does not belong to the selected municipality.'
            );
        }
    }

    private function validateFieldOfficer(Validator $validator): void
    {
        if (!$this->filled('field_officer_id')) {
            return;
        }

        $valid = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $this->integer('field_officer_id'))
            ->where('users.is_active', true)
            ->where('roles.role_name', 'Field Officer')
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'field_officer_id',
                'The selected user is not an active Field Officer.'
            );
        }
    }

    private function validateOperationalStatus(Validator $validator): void
    {
        if (!$this->filled('status_id')) {
            return;
        }

        $valid = DB::table('statuses')
            ->where('id', $this->integer('status_id'))
            ->whereIn('status_name', ['Active', 'Inactive'])
            ->exists();

        if (!$valid) {
            $validator->errors()->add('status_id', 'Association status must be Active or Inactive.');
        }
    }

    private function validateRepresentative(Validator $validator, Association $association): void
    {
        if (!$this->filled('representative_member_id')) {
            return;
        }

        $valid = DB::table('members')
            ->where('id', $this->integer('representative_member_id'))
            ->where('association_id', $association->id)
            ->where('is_archived', false)
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'representative_member_id',
                'The selected representative is not an active member of this association.'
            );
        }
    }

    private function validateNormalizedDuplicate(Validator $validator, Association $association): void
    {
        if (!$this->filled(['name', 'area_unit_id'])) {
            return;
        }

        $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $this->input('name'))));

        $duplicate = DB::table('associations')
            ->where('area_unit_id', $this->integer('area_unit_id'))
            ->where('id', '!=', $association->id)
            ->whereRaw("LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')) = ?", [$normalizedName])
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'name',
                'An association with this name already exists in the selected municipality.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'area_unit_id.required' => 'Please select a municipality.',
            'sub_unit_id.required' => 'Please select a barangay.',
            'date_joined.before_or_equal' => 'The date joined must not be later than today.',
        ];
    }
}