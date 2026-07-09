<?php

// app/Models/AreaUnit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaUnit extends Model
{
    protected $table = 'area_units';

    protected $fillable = [
        'name',
        'province',
        'address',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    /** Barangays that belong to this municipality. */
    public function subUnits()
    {
        return $this->hasMany(SubUnit::class);
    }
}