<?php

// app/Models/Project.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'association_id',
        'title',
        'commodity_type',
        'program_component_id',
        'implementation_date',
        'budget',
        'status_id',
        'remarks',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'implementation_date' => 'date',
            'budget' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function programComponent(): BelongsTo
    {
        return $this->belongsTo(ProgramComponent::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}