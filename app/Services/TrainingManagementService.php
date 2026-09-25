<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Member;
use App\Models\Status;
use App\Models\Training;
use App\Support\TrainingCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrainingManagementService
{
    public const ATTENDANCE_STATUSES = ['Pending', 'Present', 'Absent'];

    public function save(array $data, int $actorId, ?Training $training = null): Training
    {
        return DB::transaction(function () use ($data, $actorId, $training): Training {
            $editing = $training !== null;
            if ($editing) {
                $training = $this->lockedTraining($training);
            }
            $this->activeAssociation((int) $data['association_id']);
            if ($editing && (int) $training->association_id !== (int) $data['association_id'] && $training->participants()->exists()) {
                $this->invalid('association_id', 'The association cannot change while this training has participants. Remove the participants first.');
            }
            // A changed date must not place recorded attendance in the future.
            if ($editing && $data['date_conducted'] > TrainingCalendar::today()
                && $training->participants()->whereHas('attendanceStatus', fn ($query) => $query->whereIn('status_name', ['Present', 'Absent']))->exists()) {
                $this->invalid('date_conducted', 'Reset recorded attendance to Pending before moving this training to a future date.');
            }
            $training ??= new Training;
            $training->fill($data);
            if (! $editing) {
                $training->is_archived = false;
            }
            $training->save();
            $this->audit($actorId, $editing ? 'UPDATE' : 'CREATE', $training, $editing ? 'Updated training details.' : 'Created training.');

            return $training;
        }, 3);
    }

    public function archive(Training $training, bool $archived, int $actorId): void
    {
        DB::transaction(function () use ($training, $archived, $actorId): void {
            $training = Training::query()->lockForUpdate()->findOrFail($training->id);
            if ($training->is_archived === $archived) {
                return;
            }
            if (! $archived) {
                $this->activeAssociation((int) $training->association_id);
            }
            $training->update(['is_archived' => $archived]);
            $this->audit($actorId, $archived ? 'ARCHIVE' : 'RESTORE', $training, $archived ? 'Archived training; attendance retained.' : 'Restored training.');
        }, 3);
    }

    public function addParticipant(Training $training, int $memberId, int $actorId): void
    {
        DB::transaction(function () use ($training, $memberId, $actorId): void {
            $training = $this->lockedTraining($training);
            $this->activeAssociation((int) $training->association_id);
            $member = Member::query()->lockForUpdate()->find($memberId);
            if (! $member || $member->is_archived || (int) $member->association_id !== (int) $training->association_id) {
                $this->invalid('member_id', 'Choose an active member of this training’s association.');
            }
            // The parent lock serializes registrations; the database also has a unique pair constraint.
            if ($training->participants()->where('member_id', $memberId)->exists()) {
                $this->invalid('member_id', 'This member is already registered for the training.');
            }
            $pending = Status::query()->where('status_name', 'Pending')->first();
            if (! $pending) {
                $this->invalid('member_id', 'The Pending attendance status is unavailable. Please contact the system administrator.');
            }
            $training->participants()->create(['member_id' => $memberId, 'attendance_status_id' => $pending->id]);
            $this->audit($actorId, 'CREATE', $training, 'Registered participant member #'.$memberId.'.');
        }, 3);
    }

    public function attendance(Training $training, int $participantId, int $statusId, int $actorId): void
    {
        DB::transaction(function () use ($training, $participantId, $statusId, $actorId): void {
            $training = $this->lockedTraining($training);
            $this->activeAssociation((int) $training->association_id);
            $participant = $training->participants()->lockForUpdate()->findOrFail($participantId);
            $status = Status::query()->whereKey($statusId)->whereIn('status_name', self::ATTENDANCE_STATUSES)->first();
            if (! $status) {
                $this->invalid('attendance_status_id', 'Choose Pending, Present, or Absent.');
            }
            if ($status->status_name !== 'Pending' && ! $training->canRecordAttendance()) {
                $this->invalid('attendance_status_id', 'Attendance can only be recorded on or after the training date.');
            }
            if ((int) $participant->attendance_status_id === $statusId) {
                return;
            }
            $participant->update(['attendance_status_id' => $statusId]);
            $this->audit($actorId, 'UPDATE', $training, 'Set participant #'.$participantId.' attendance to '.$status->status_name.'.');
        }, 3);
    }

    public function removeParticipant(Training $training, int $participantId, int $actorId): void
    {
        DB::transaction(function () use ($training, $participantId, $actorId): void {
            $training = $this->lockedTraining($training);
            $this->activeAssociation((int) $training->association_id);
            $participant = $training->participants()->lockForUpdate()->findOrFail($participantId);
            $memberId = $participant->member_id;
            $participant->delete();
            $this->audit($actorId, 'DELETE', $training, 'Removed participant member #'.$memberId.' and their attendance entry.');
        }, 3);
    }

    private function lockedTraining(Training $training): Training
    {
        $training = Training::query()->lockForUpdate()->findOrFail($training->id);
        if ($training->is_archived) {
            $this->invalid('training', 'Restore this archived training before making changes.');
        }

        return $training;
    }

    private function activeAssociation(int $id): void
    {
        $association = Association::query()->lockForUpdate()->find($id);
        if (! $association || $association->is_archived) {
            $this->invalid('association_id', 'Choose an active association. Restore the association before managing its training records.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function audit(int $actorId, string $action, Training $training, string $details): void
    {
        // Keep the audit and business change in one transaction so failures cannot leave partial saves.
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $action,
            'module' => 'Training Management',
            'record_id' => $training->id,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}
