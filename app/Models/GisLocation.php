<?php

// app/Models/GisLocation.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GisLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'association_id',
        'location_name',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            // Keep database decimals as text; map display may use numbers, editing must not round them.
            'latitude' => 'string',
            'longitude' => 'string',
            'is_published' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        // Public readers must start here, never filter the administrator payload in a browser.
        return $query->where('is_published', true)->whereNull('archived_at')
            ->whereHas('association', fn (Builder $parent) => $parent->where('is_archived', false))
            ->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])
            ->whereNotNull('location_name')->whereRaw("BTRIM(location_name) <> ''");
    }
}
