<?php

// app/Http/Requests/Admin/AssignAssociationRepresentativeRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Association;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AssignAssociationRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'representative_member_id' => [
                'nullable',
                'integer',
                Rule::exists('members', 'id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $association = $this->route('association');

                if (!$association instanceof Association || !$this->filled('representative_member_id')) {
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
            },
        ];
    }
}