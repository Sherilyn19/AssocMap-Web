<?php

// app/Models/GisLocation.php

declare(strict_types=1);

namespace App\Models;

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
        'geom',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_published' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }
}