<?php

// app/Models/Member.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'association_id',
        'application_id',
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'birthday',
        'sex_id',
        'role_in_assoc',
        'beneficiary_type',
        'contact_number',
        'address',
        'date_registered',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'date_registered' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MemberApplication::class, 'application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sex(): BelongsTo
    {
        return $this->belongsTo(Sex::class);
    }

    public function reviewedApplications(): HasMany
    {
        return $this->hasMany(MemberApplication::class, 'reviewed_by_member_id');
    }
}