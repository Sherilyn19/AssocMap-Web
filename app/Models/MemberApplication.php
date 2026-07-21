<?php

// app/Models/MemberApplication.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'association_id',
        'first_name',
        'middle_name',
        'last_name',
        'birthday',
        'sex_id',
        'beneficiary_type',
        'contact_number',
        'address',
        'status_id',
        'reviewed_by_member_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function sex(): BelongsTo
    {
        return $this->belongsTo(Sex::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }
}