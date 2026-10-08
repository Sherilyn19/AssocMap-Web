<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\User;
use App\Services\MonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProductionProgressService
{
    public function __construct(
        private readonly MonitoringService $monitoring
    ) {}

    public function createQuarter(User $actor, array $input): int
    {
        $input = $this->normalize($input);

        $data = validator($input, [
            'project_id' => ['required', 'integer', 'min:1'],
            'quarter_id' => ['required', 'integer', 'exists:quarters,id'],
            'year' => ['required', 'integer', 'between:1900,2100'],
            'target_output' => [
                'required', 'numeric', 'decimal:0,2',
                'between:0,99999999.99',
            ],
            'output_unit_code' => [
                'required', Rule::in(array_keys(MonitoringService::UNITS)),
            ],
            'output_unit_spec' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'submission_token' => ['required', 'uuid'],
        ])->validate();

        $data['output_unit_spec'] = $data['output_unit_spec'] ?? null;

        if (
            in_array(
                $data['output_unit_code'],
                MonitoringService::PACKAGED_UNITS,
                true
            )
            && !$data['output_unit_spec']
        ) {
            $this->invalid(
                'output_unit_spec',
                'Enter the package size or unit specification.'
            );
        }

        return DB::transaction(function () use ($actor, $data): int {
            $actor = $this->freshActor($actor);
            $project = $this->lockProject($actor, (int) $data['project_id']);

            [$start] = $this->period(
                (int) $data['year'],
                (int) $data['quarter_id']
            );

            if ($start->isAfter(CarbonImmutable::today('Asia/Manila'))) {
                $this->invalid('quarter_id', 'Future quarters cannot be opened.');
            }

            if (
                DB::table('monitoring_production')
                    ->where('submission_token', $data['submission_token'])
                    ->exists()
            ) {
                $this->invalid(
                    'submission_token',
                    'This submission was already saved. Reload the register.'
                );
            }

            if (
                DB::table('monitoring_production')
                    ->where('project_id', $project->id)
                    ->where('year', $data['year'])
                    ->where('quarter_id', $data['quarter_id'])
                    ->exists()
            ) {
                $this->invalid(
                    'quarter_id',
                    'This project already has a record for that quarter.'
                );
            }

            // Keep the existing project-wide production unit rules.
            $this->monitoring->confirmProductionUnit($project, $data);

            $id = (int) DB::table('monitoring_production')->insertGetId([
                ...$data,
                'association_id' => $project->association_id,
                'actual_output' => '0.00',
                'tracking_mode' => 'entries',
                'revision' => 1,
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $after = DB::table('monitoring_production')->find($id);

            $this->audit(
                $actor,
                $id,
                'CREATE',
                'Quarter opened',
                null,
                (array) $after,
                null,
                null,
                null
            );

            return $id;
        }, 3);
    }

    public function change(User $actor, int $quarterId, array $input): void
    {
        $input = $this->normalize($input);

        $command = validator($input, [
            'operation' => [
                'required',
                Rule::in(['target', 'entry_add', 'entry_edit', 'entry_void']),
            ],
            'revision' => ['required', 'integer', 'min:1'],
        ])->validate();

        DB::transaction(function () use (
            $actor,
            $quarterId,
            $input,
            $command
        ): void {
            $actor = $this->freshActor($actor);

            // Resolve ownership before locking or exposing the requested record.
            $accessible = $this->monitoring->records('production', $actor)
                ->where('m.id', $quarterId)
                ->first();

            abort_unless($accessible, 404);

            $project = $this->lockProject(
                $actor,
                (int) $accessible->project_id
            );

            $quarter = DB::table('monitoring_production')
                ->where('id', $quarterId)
                ->where('project_id', $project->id)
                ->where('association_id', $project->association_id)
                ->lockForUpdate()
                ->first();

            abort_unless($quarter, 404);

            if ($quarter->tracking_mode !== 'entries') {
                $this->invalid(
                    'operation',
                    'Historical summaries must be reconciled separately before adding dated entries.'
                );
            }

            if ((int) $quarter->revision !== (int) $command['revision']) {
                $this->invalid(
                    'revision',
                    'This quarter changed after you opened it. Close and reopen Achievement.'
                );
            }

            $before = (array) $quarter;
            $entryBefore = null;
            $entryAfter = null;
            $reason = null;

            if ($command['operation'] === 'target') {
                $reason = $this->changeTarget($quarterId, $input);
                $event = 'Quarter target updated';
            } else {
                [$entryBefore, $entryAfter, $reason] =
                    $this->changeEntry(
                        $actor,
                        $quarter,
                        $command['operation'],
                        $input
                    );

                $event = match ($command['operation']) {
                    'entry_add' => 'Production entry added',
                    'entry_edit' => 'Production entry edited',
                    default => 'Production entry voided',
                };
            }

            // PostgreSQL performs decimal summation; voided entries do not count.
            $total = DB::table('production_progress_entries')
                ->where('monitoring_production_id', $quarterId)
                ->whereNull('voided_at')
                ->sum('quantity');

            if ((float) $total > 99999999.99) {
                $this->invalid(
                    'quantity',
                    'The quarterly total exceeds the supported output limit.'
                );
            }

            DB::table('monitoring_production')
                ->where('id', $quarterId)
                ->update([
                    'actual_output' => $total,
                    'revision' => (int) $quarter->revision + 1,
                    'updated_at' => now(),
                ]);

            $after = DB::table('monitoring_production')->find($quarterId);

            $this->audit(
                $actor,
                $quarterId,
                'UPDATE',
                $event,
                $before,
                (array) $after,
                $entryBefore,
                $entryAfter,
                $reason
            );
        }, 3);
    }

    private function changeTarget(int $quarterId, array $input): string
    {
        $data = validator($input, [
            'target_output' => [
                'required', 'numeric', 'decimal:0,2',
                'between:0,99999999.99',
            ],
            'correction_reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        // Even before the first entry, retain a reason for changing a saved target.
        DB::table('monitoring_production')
            ->where('id', $quarterId)
            ->update(['target_output' => $data['target_output']]);

        return $data['correction_reason'];
    }

    private function changeEntry(
        User $actor,
        object $quarter,
        string $operation,
        array $input
    ): array {
        $before = null;
        $reason = null;
        $entry = null;

        if ($operation !== 'entry_add') {
            $identity = validator($input, [
                'entry_id' => ['required', 'integer', 'min:1'],
                'correction_reason' => ['required', 'string', 'max:2000'],
            ])->validate();

            $entry = DB::table('production_progress_entries')
                ->where('monitoring_production_id', $quarter->id)
                ->where('id', $identity['entry_id'])
                ->lockForUpdate()
                ->first();

            abort_unless($entry, 404);

            if ($entry->voided_at !== null) {
                $this->invalid('entry_id', 'This entry has already been voided.');
            }

            $before = (array) $entry;
            $reason = $identity['correction_reason'];
        }

        if ($operation === 'entry_void') {
            DB::table('production_progress_entries')
                ->where('id', $entry->id)
                ->update([
                    'voided_at' => now(),
                    'updated_at' => now(),
                ]);

            return [
                $before,
                (array) DB::table('production_progress_entries')->find($entry->id),
                $reason,
            ];
        }

        $data = validator($input, [
            'produced_on' => [
                'required', 'date_format:Y-m-d',
                'before_or_equal:'.now('Asia/Manila')->toDateString(),
            ],
            'quantity' => [
                'required', 'numeric', 'decimal:0,2',
                'gt:0', 'max:99999999.99',
            ],
            'description' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        [$start, $end] = $this->period(
            (int) $quarter->year,
            (int) $quarter->quarter_id
        );

        $date = CarbonImmutable::parse($data['produced_on'], 'Asia/Manila');

        if ($date->lt($start) || $date->gt($end)) {
            $this->invalid(
                'produced_on',
                'The production date must fall within this quarter.'
            );
        }

        $data['remarks'] = $data['remarks'] ?? null;
        $data['updated_at'] = now();

        if ($operation === 'entry_add') {
            $id = DB::table('production_progress_entries')->insertGetId([
                ...$data,
                'monitoring_production_id' => $quarter->id,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);
        } else {
            $id = $entry->id;

            DB::table('production_progress_entries')
                ->where('id', $id)
                ->update($data);
        }

        return [
            $before,
            (array) DB::table('production_progress_entries')->find($id),
            $reason,
        ];
    }

    public function period(int $year, int $quarterId): array
    {
        $name = DB::table('quarters')->where('id', $quarterId)
            ->value('quarter_name');

        if (!preg_match('/^Q([1-4])$/', (string) $name, $match)) {
            $this->invalid('quarter_id', 'The quarter configuration is invalid.');
        }

        $start = CarbonImmutable::create(
            $year,
            ((int) $match[1] - 1) * 3 + 1,
            1,
            0,
            0,
            0,
            'Asia/Manila'
        );

        return [$start, $start->addMonths(3)->subDay()];
    }

    private function freshActor(User $actor): User
    {
        $fresh = User::with('role')->sharedLock()->findOrFail($actor->id);
        $this->monitoring->authorize($fresh);

        return $fresh;
    }

    private function lockProject(User $actor, int $projectId): object
    {
        $project = $this->monitoring->projects($actor)
            ->where('p.id', $projectId)
            ->select('p.*', 'a.is_archived as association_archived')
            ->lockForUpdate()
            ->first();

        abort_unless($project, 404);

        if ($project->is_archived || $project->association_archived) {
            $this->invalid(
                'project_id',
                'Archived projects and associations are read-only.'
            );
        }

        return $project;
    }

    private function audit(
        User $actor,
        int $quarterId,
        string $action,
        string $event,
        ?array $before,
        array $after,
        ?array $entryBefore,
        ?array $entryAfter,
        ?string $reason
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actor->id,
            'action_type' => $action,
            'module' => 'Production Progress',
            'record_id' => $quarterId,
            'details' => json_encode([
                'event' => $event,
                'type' => 'production',
                'project_id' => $after['project_id'],
                'before' => $before,
                'after' => $after,
                'entry_before' => $entryBefore,
                'entry_after' => $entryAfter,
                'correction_reason' => $reason,
            ], JSON_THROW_ON_ERROR),
            'performed_at' => now(),
        ]);
    }

    private function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        return $input;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}