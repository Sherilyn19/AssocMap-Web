<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberDraft extends Model
{
    // Workflow services assign ownership and state explicitly.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'revision' => 'integer',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MemberApplication::class);
    }
}