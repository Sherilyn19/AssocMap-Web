<?php

namespace App\Http\Requests\FieldOfficerUser;

use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = app(SessionUserResolver::class)->resolve($this);

        return $actor->is_active && $actor->role?->role_name === 'Field Officer';
    }

    public function rules(): array
    {
        return [
            'delivery_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'item_name' => ['prohibited'], 'quantity' => ['prohibited'], 'unit' => ['prohibited'],
            'unit_cost' => ['prohibited'], 'status_id' => ['prohibited'], 'project_id' => ['prohibited'],
            'association_id' => ['prohibited'], 'is_archived' => ['prohibited'],
        ];
    }
}
