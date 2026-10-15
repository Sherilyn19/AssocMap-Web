<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssociationAssistanceService
{
    public function authorized(User $actor, int $id, bool $lock = false): Association
    {
        $userQuery = User::with('role');

        if ($lock) {
            $userQuery->sharedLock();
        }

        $user = $userQuery->findOrFail($actor->id);

        abort_unless(
            $user->is_active
            && in_array($user->role?->role_name, ['System Administrator', 'Field Officer'], true),
            403
        );

        $query = Association::query();

        if ($user->role->role_name === 'Field Officer') {
            $query->where('field_officer_id', $user->id);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    public function pending(int $id): array
    {
        // Preflight and final validation use the same pending-work calculation.
        $row = DB::selectOne('SELECT * FROM am_assistance_pending(?)', [$id]);

        return array_map('intval', (array) $row);
    }

    public function phrase(int $id, string $status = 'terminated'): string
    {
        $verb = match ($status) {
            'proposal' => 'PROPOSE',
            'accepted' => 'ACCEPT',
            'terminated' => 'TERMINATE',
        };

        return $verb.' ASSOC-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function update(User $actor, int $id, array $data): void
    {
        DB::transaction(function () use ($actor, $id, $data): void {
            $record = $this->authorized($actor, $id, true);

            abort_if($record->is_archived, 403, 'Archived associations cannot be changed.');

            $before = $record->assistance_status;
            $after = $data['assistance_status'];

            if ($before === 'terminated') {
                $this->invalid('Assistance terminated. Association records are read-only.');
            }

            if (($data['previous_status'] ?? null) !== $before) {
                $this->invalid('This status changed. Reload the association before saving.');
            }

            // Only the next lifecycle status is permitted.
            $next = match ($before) {
                null => 'proposal',
                'proposal' => 'accepted',
                'accepted' => 'terminated',
                default => null,
            };

            if ($after !== $next) {
                $this->invalid('Follow Proposal → Accepted → Terminated without skipping or reversing.');
            }

            if (($data['assistance_confirmation'] ?? '') !== $this->phrase($id, $after)) {
                throw ValidationException::withMessages([
                    'assistance_confirmation' => 'Type the confirmation phrase exactly as shown.',
                ]);
            }

            $date = $data['assessment_date'];
            $previousDate = $record->getRawOriginal('assistance_assessed_on');
            $reason = trim($data['reason']);

            // Keep existing unknown dates unknown; do not invent historical evidence.
            if ($previousDate !== null && $date < substr((string) $previousDate, 0, 10)) {
                throw ValidationException::withMessages([
                    'assessment_date' => 'The date cannot precede the previous assistance-status date.',
                ]);
            }

            if ($after === 'terminated') {
                $pending = $this->pending($id);

                if (array_sum($pending) > 0) {
                    $this->invalid(sprintf(
                        'Termination blocked: %d pending applications, %d unfinished drafts and %d pending attendance entries. Resolve these records first.',
                        $pending['applications'],
                        $pending['drafts'],
                        $pending['attendance']
                    ));
                }

                $record->assistance_termination_reason = $reason;
                $record->assistance_terminated_by = $actor->id;
                $record->assistance_terminated_at = now();
            }

            // Reuse the existing date column for the current assistance decision.
            // Every prior date and reason remains in the audit history.
            $record->assistance_assessed_on = $date;
            $record->assistance_status = $after;
            $record->save();

            DB::table('audit_logs')->insert([
                'user_id' => $actor->id,
                'action_type' => 'UPDATE',
                'module' => 'Association',
                'record_id' => $id,
                'performed_at' => now(),
                'details' => json_encode([
                    'field' => 'assistance_status',
                    'before' => $before,
                    'after' => $after,
                    'previous_assessment_date' => $previousDate,
                    'assessment_date' => $date,
                    'reason' => $reason,
                ], JSON_THROW_ON_ERROR),
            ]);
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages([
            'assistance_status' => $message,
        ]);
    }
}