<?php

// app/Models/ProgramComponent.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProgramComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function associations(): HasMany
    {
        return $this->hasMany(Association::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}