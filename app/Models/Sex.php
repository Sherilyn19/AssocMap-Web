<?php

// app/Models/Sex.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Sex extends Model
{
    use HasFactory;

    protected $table = 'sex';

    protected $fillable = [
        'sex_name',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function memberApplications(): HasMany
    {
        return $this->hasMany(MemberApplication::class);
    }
}