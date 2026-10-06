<?php

namespace App\Http\Requests\FieldOfficerUser;

use App\Services\SessionUserResolver;
use App\Services\TrainingManagementService;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = app(SessionUserResolver::class)->resolve($this);

        return $actor->is_active
            && $actor->role?->role_name === 'Field Officer';
    }

    public function rules(): array
    {
        // Store has no training ID; update has an existing training ID.
        return TrainingManagementService::officerRules(
            $this->route('training') === null
        );
    }

    public function attributes(): array
    {
        return [
            'stage' => 'training purpose',
            'date_conducted' => 'start date',
            'end_date' => 'end date',
            'training_cost' => 'approved training cost',
            'approval_confirmed' => 'external approval confirmation',
        ];
    }
}