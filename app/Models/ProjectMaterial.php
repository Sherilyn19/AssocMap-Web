<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Project material supplied, issued, or delivered for a project.
 *
 * Project materials are intentionally scoped through project_id so an
 * administrator cannot accidentally attach a material to another project.
 */
final class ProjectMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'item_name',
        'quantity',
        'unit',
        'unit_cost',
        'status_id',
        'delivery_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'delivery_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Material cost is derived from quantity Ã— unit cost.
     *
     * No duplicated total-cost database field is required.
     */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->quantity * (float) ($this->unit_cost ?? 0);
    }
}