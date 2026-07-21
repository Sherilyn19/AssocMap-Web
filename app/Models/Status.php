<?php

// app/Models/Status.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_name',
    ];

    public function associations(): HasMany
    {
        return $this->hasMany(Association::class);
    }

    public function memberApplications(): HasMany
    {
        return $this->hasMany(MemberApplication::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}