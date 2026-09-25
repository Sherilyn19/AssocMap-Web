<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TrainingParticipant extends Model
{
    // The existing participant table has no created_at or updated_at columns.
    public $timestamps = false;

    protected $fillable = ['training_id', 'member_id', 'attendance_status_id'];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'attendance_status_id');
    }
}
