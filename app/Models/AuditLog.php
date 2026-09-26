<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action_type', 'module', 'record_id', 'details', 'performed_at'];

    protected function casts(): array
    {
        return ['performed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->performed_at ??= now();
        });
        // Database triggers also protect bulk queries, which skip model events.
        static::updating(fn () => throw new LogicException('Audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
