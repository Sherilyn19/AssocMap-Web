<?php

// app/Models/SubUnit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubUnit extends Model
{
    protected $table = 'sub_units';

    protected $fillable = [
        'area_unit_id',
        'name',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    /** The municipality this barangay belongs to. */
    public function areaUnit()
    {
        return $this->belongsTo(AreaUnit::class);
    }
}