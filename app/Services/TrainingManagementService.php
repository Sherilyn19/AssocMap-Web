<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Member;
use App\Models\Status;
use App\Models\Training;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrainingManagementService
{
    public const ATTENDANCE_STATUSES = ['Pending', 'Present', 'Absent'];

    /**
     * Shared validation for FO requests and direct service calls.
     * Approval confirmation is required only when recording a new training.
     */
    public static function officerRules(bool $creating): array
    {
        $rules = [
            'stage' => [
                'required',
                \Illuminate\Validation\Rule::in(array_keys(Training::STAGES)),
            ],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'is_archived' => ['prohibited'],
            'external_approval_recorded_at' => ['prohibited'],
            'schedule_locked_at' => ['prohibited'],
        ];

        if ($creating) {
            return $rules + [
                'association_id' => ['required', 'integer', 'min:1'],
                'title' => ['required', 'string', 'max:255'],
                'program_component_id' => [
                    'required', 'integer', 'exists:program_components,id',
                ],
                'training_type' => ['required', 'string', 'max:100'],
                'venue' => ['required', 'string', 'max:255'],
                'conducted_by' => ['required', 'string', 'max:255'],
                'date_conducted' => ['required', 'date_format:Y-m-d'],
                'end_date' => [
                    'required', 'date_format:Y-m-d', 'after_or_equal:date_conducted',
                ],
                'training_cost' => [
                    'required', 'numeric', 'min:0',
                    'max:999999999999.99', 'decimal:0,2',
                ],
                'approval_confirmed' => ['required', 'accepted'],
            ];
        }

        return $rules + [
            // FO cannot change the approved identity, budget, or association.
            'association_id' => ['prohibited'],
            'title' => ['prohibited'],
            'program_component_id' => ['prohibited'],
            'training_type' => ['prohibited'],
            'venue' => ['prohibited'],
            'conducted_by' => ['prohibited'],
            'training_cost' => ['prohibited'],
            'approval_confirmed' => ['prohibited'],

            // Both dates must be supplied together when changing the schedule.
            'date_conducted' => [
                'sometimes', 'required', 'required_with:end_date', 'date_format:Y-m-d',
            ],
            'end_date' => [
                'sometimes', 'required', 'required_with:date_conducted',
                'date_format:Y-m-d', 'after_or_equal:date_conducted',
            ],
        ];
    }

    /** Record a training that has already received approval outside AssocMap. */
    public function createOfficerTraining(
        array $data,
        \App\Models\User $actor
    ): Training {
        return DB::transaction(function () use ($data, $actor): Training {
            $data = validator($data, self::officerRules(true))->validate();

            // Recheck the current assignment inside the transaction.
            $association = app(FieldOfficerUserAccess::class)
                ->lockAssociation($actor, (int) $data['association_id']);

            $training = new Training;

            $training->fill(\Illuminate\Support\Arr::only($data, [
                'title', 'program_component_id', 'training_type', 'venue',
                'date_conducted', 'end_date', 'stage', 'conducted_by', 'remarks',
            ]));

            // These values come from validated data and the authorized association.
            $training->forceFill([
                'association_id' => $association->id,
                'training_cost' => $data['training_cost'],
                'external_approval_recorded_at' => now(),
                'is_archived' => false,
            ])->save();

            $this->audit(
                (int) $actor->id,
                'CREATE',
                $training,
                'Recorded externally approved training and budget. '
                    .'FO confirmed completion of the external hearing and approval process.'
            );

            return $training;
        }, 3);
    }

    /** Edit only purpose, remarks, and a schedule whose registration has not started. */
    public function updateOfficerInformation(
        Training $training,
        array $data,
        \App\Models\User $actor
    ): void {
        DB::transaction(function () use ($training, $data, $actor): void {
            // Match the training-first lock order used by attendance registration.
            $training = $this->lockedTraining($training);

            app(FieldOfficerUserAccess::class)
                ->lockAssociation($actor, (int) $training->association_id);

            $data = validator($data, self::officerRules(false))->validate();

            $hasStart = array_key_exists('date_conducted', $data);
            $hasEnd = array_key_exists('end_date', $data);

            if ($hasStart !== $hasEnd) {
                $this->invalid('date_conducted', 'Provide both the start and end dates.');
            }

            if ($hasStart) {
                $datesChanged =
                    $data['date_conducted'] !== $training->date_conducted?->toDateString()
                    || $data['end_date'] !== $training->end_date?->toDateString();

                // Check both the permanent marker and existing participant records.
                if ($datesChanged && (
                    $training->schedule_locked_at !== null
                    || $training->participants()->exists()
                )) {
                    $this->invalid(
                        'date_conducted',
                        'Dates cannot change after participant registration has started.'
                    );
                }
            }

            $before = \Illuminate\Support\Arr::only($training->getAttributes(), [
                'stage', 'remarks', 'date_conducted', 'end_date',
            ]);

            $training->fill(\Illuminate\Support\Arr::only($data, [
                'stage', 'remarks', 'date_conducted', 'end_date',
            ]));

            if (! $training->isDirty()) {
                return;
            }

            $training->save();

            $this->audit(
                (int) $actor->id,
                'UPDATE',
                $training,
                json_encode([
                    'before' => $before,
                    'after' => \Illuminate\Support\Arr::only(
                        $training->getAttributes(),
                        ['stage', 'remarks', 'date_conducted', 'end_date']
                    ),
                ], JSON_THROW_ON_ERROR)
            );
        }, 3);
    }

    /** Archive the assigned training while retaining participants and attendance. */
    public function archiveOfficerTraining(
        Training $training,
        \App\Models\User $actor
    ): void {
        DB::transaction(function () use ($training, $actor): void {
            $training = Training::query()->lockForUpdate()->findOrFail($training->id);

            app(FieldOfficerUserAccess::class)
                ->lockAssociation($actor, (int) $training->association_id);

            if ($training->is_archived) {
                return;
            }

            $training->update(['is_archived' => true]);

            $this->audit(
                (int) $actor->id,
                'ARCHIVE',
                $training,
                'Archived training; participant and attendance history retained.'
            );
        }, 3);
    }

    private function authorizeAttendance(Training $training, int $actorId): void
    {
        $actor = \App\Models\User::with('role')->sharedLock()->findOrFail($actorId);
        abort_unless($actor->is_active && in_array($actor->role?->role_name, ['System Administrator', 'Field Officer'], true), 403);
        if ($actor->role->role_name === 'Field Officer') {
            app(FieldOfficerUserAccess::class)->lockAssociation($actor, (int) $training->association_id);
        }
    }

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
            // Keep the historical schedule immutable, including for direct service callers.
            $data = \Illuminate\Support\Arr::only($data, ['association_id', 'title', 'program_component_id', 'training_type', 'venue', 'date_conducted', 'end_date', 'stage', 'conducted_by', 'remarks']);
            if ($editing) {
                unset($data['date_conducted'], $data['end_date']);
            }
            validator($data, [
                'stage' => ['required', \Illuminate\Validation\Rule::in(array_keys(Training::STAGES))],
                'date_conducted' => $editing ? ['exclude'] : ['required', 'date_format:Y-m-d'],
                'end_date' => $editing ? ['exclude'] : ['required', 'date_format:Y-m-d', 'after_or_equal:date_conducted'],
            ])->validate();
            $previousStage = $training?->stage;
            $training ??= new Training;
            $training->fill($data);
            if (! $editing) {
                $training->is_archived = false;
            }
            $training->save();
            $this->audit($actorId, $editing ? 'UPDATE' : 'CREATE', $training, $editing ? 'Updated training details; stage: '.($previousStage ?? 'Not recorded').' → '.$training->stage.'.' : 'Created training; stage: '.$training->stage.'.');

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
            $this->authorizeAttendance($training, $actorId);
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
            // Lock the schedule permanently when the first participant is registered.
            // This marker remains even if an administrator later removes the participant.
            if ($training->schedule_locked_at === null) {
                $training->forceFill(['schedule_locked_at' => now()])->save();
            }
            $training->participants()->create(['member_id' => $memberId, 'attendance_status_id' => $pending->id]);
            $this->audit($actorId, 'CREATE', $training, 'Registered participant member #'.$memberId.'.');
        }, 3);
    }

    public function attendance(Training $training, int $participantId, int $statusId, int $actorId): void
    {
        DB::transaction(function () use ($training, $participantId, $statusId, $actorId): void {
            $training = $this->lockedTraining($training);
            $this->authorizeAttendance($training, $actorId);
            $this->activeAssociation((int) $training->association_id);
            $participant = $training->participants()->lockForUpdate()->findOrFail($participantId);
            $member = Member::query()->lockForUpdate()->find($participant->member_id);
            abort_unless($member && ! $member->is_archived && (int) $member->association_id === (int) $training->association_id, 404);
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
