<?php

// app/Models/Training.php

declare(strict_types=1);

namespace App\Models;

use App\Support\TrainingCalendar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Training extends Model
{
    use HasFactory;

    protected $fillable = [
        'association_id',
        'title',
        'program_component_id',
        'training_type',
        'venue',
        'date_conducted',
        'training_cost',
        'conducted_by',
        'remarks',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'date_conducted' => 'date',
            'training_cost' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function programComponent(): BelongsTo
    {
        return $this->belongsTo(ProgramComponent::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function canRecordAttendance(): bool
    {
        // Compare date strings so a UTC cast does not shift the local training day.
        return $this->date_conducted !== null
            && $this->date_conducted->toDateString() <= TrainingCalendar::today();
    }
}
