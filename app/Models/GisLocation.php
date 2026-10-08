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
        // Qualify GIS columns because filter choices join related tables.
        // Public records must also belong to an active, non-archived association.
        return $query
            ->where('gis_locations.is_published', true)
            ->whereNull('gis_locations.archived_at')
            ->whereHas('association', function (Builder $association): void {
                $association
                    ->where('associations.is_archived', false)
                    ->whereHas(
                        'status',
                        fn (Builder $status) => $status
                            ->where('statuses.status_name', 'Active')
                    );
            })
            ->whereBetween('gis_locations.latitude', [-90, 90])
            ->whereBetween('gis_locations.longitude', [-180, 180])
            ->whereNotNull('gis_locations.location_name')
            ->whereRaw("BTRIM(gis_locations.location_name) <> ''");
    }
}
