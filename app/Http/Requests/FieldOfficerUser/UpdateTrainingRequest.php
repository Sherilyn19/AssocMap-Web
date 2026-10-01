<?php

namespace App\Http\Requests\FieldOfficerUser;

use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = app(SessionUserResolver::class)->resolve($this);

        return $actor->is_active && $actor->role?->role_name === 'Field Officer';
    }

    public function rules(): array
    {
        return [
            'venue' => ['required', 'string', 'max:255'],
            'conducted_by' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'title' => ['prohibited'], 'training_type' => ['prohibited'],
            'association_id' => ['prohibited'], 'program_component_id' => ['prohibited'],
            'date_conducted' => ['prohibited'], 'end_date' => ['prohibited'],
            'stage' => ['prohibited'], 'training_cost' => ['prohibited'], 'is_archived' => ['prohibited'],
        ];
    }
}
