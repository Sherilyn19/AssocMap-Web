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

    public const STAGES = ['proposal' => 'Initiation / Proposal', 'accepted' => 'Accepted', 'terminated' => 'Termination'];

    protected $fillable = [
        'association_id',
        'title',
        'program_component_id',
        'training_type',
        'venue',
        'date_conducted',
        'end_date',
        'stage',
        'conducted_by',
        'remarks',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'date_conducted' => 'date',
            'end_date' => 'date',
            'is_archived' => 'boolean',
            // Keep money precise and workflow markers readable as dates.
            'training_cost' => 'decimal:2',
            'external_approval_recorded_at' => 'datetime',
            'schedule_locked_at' => 'datetime',
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

    /** Count the whole register, including participants outside the current page. */
    public function scopeWithAttendanceSummary(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->withCount([
            'participants as registered_count' => fn ($q) => $q->whereHas('member', fn ($member) => $member->whereColumn('members.association_id', 'trainings.association_id')),
            'participants as recorded_count' => fn ($q) => $q
                ->whereHas('member', fn ($member) => $member->whereColumn('members.association_id', 'trainings.association_id'))
                ->whereHas('attendanceStatus', fn ($status) => $status->whereIn('status_name', ['Present', 'Absent'])),
        ]);
    }

    public function canRecordAttendance(): bool
    {
        // Compare date strings so a UTC cast does not shift the local training day.
        return $this->date_conducted !== null
            && $this->date_conducted->toDateString() <= TrainingCalendar::today();
    }
}
