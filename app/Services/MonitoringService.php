<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MonitoringService
{
    public const TYPES = [
        'production' => 'Production',
        'income' => 'Income',
        'materials' => 'Materials',
    ];

    public const CONDITIONS = ['Good', 'Damaged', 'For Repair'];

    // Production output units are separate from equipment/material units.
    public const UNITS = [
        'g' => 'Grams (g)',
        'kg' => 'Kilograms (kg)',
        'mt' => 'Metric tons (MT)',
        'pcs' => 'Pieces (pcs)',
        'count' => 'Individuals (count)',
        'ml' => 'Milliliters (mL)',
        'l' => 'Liters (L)',
        'pack' => 'Packs',
        'bottle' => 'Bottles',
        'jar' => 'Jars',
        'can' => 'Cans',
        'box' => 'Boxes',
        'bag' => 'Bags',
        'tray' => 'Trays',
    ];

    public const PACKAGED_UNITS = [
        'pack', 'bottle', 'jar', 'can', 'box', 'bag', 'tray',
    ];

    public function authorize(User $actor): void
    {
        abort_unless(
            $actor->is_active
            && in_array($actor->role?->role_name, [
                'System Administrator',
                'Field Officer',
            ], true),
            403
        );
    }

    public function projects(User $actor): Builder
    {
        $this->authorize($actor);

        $query = DB::table('projects as p')
            ->join('associations as a', 'a.id', '=', 'p.association_id');

        // Every child record inherits its project's current association scope.
        if ($actor->role->role_name === 'Field Officer') {
            $query->where('a.field_officer_id', $actor->id);
        }

        return $query;
    }

    public function records(string $type, User $actor): Builder
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        $query = $this->projects($actor);

        if ($type === 'materials') {
            $query
                ->join('project_materials as pm', 'pm.project_id', '=', 'p.id')
                ->join('monitoring_materials as m', 'm.project_material_id', '=', 'pm.id')
                ->leftJoin('statuses as s', 's.id', '=', 'm.condition_status_id');
        } else {
            $query->join('monitoring_'.$type.' as m', function ($join): void {
                $join->on('m.project_id', '=', 'p.id')
                    ->on('m.association_id', '=', 'a.id');
            });

            if ($type === 'production') {
                $query->join('quarters as q', 'q.id', '=', 'm.quarter_id');
            }
        }

        $query
            ->leftJoin('statuses as project_status', 'project_status.id', '=', 'p.status_id')
            ->select(
                'm.*',
                'p.id as project_id',
                'p.title as project_title',
                'p.terminated_on',
                'project_status.status_name as project_status_name',
                'a.name as association_name',
                'p.is_archived as project_archived',
                'a.is_archived as association_archived'
            );

        if ($type === 'materials') {
            $query->addSelect(
                'pm.item_name',
                'pm.archived_at as material_archived_at',
                's.status_name'
            );
        }

        if ($type === 'production') {
            $query->addSelect('q.quarter_name');

            // Never combine unknown or different units into a misleading total.
            $query->selectSub(function ($annual): void {
                $annual->from('monitoring_production as annual')
                    ->whereColumn('annual.project_id', 'p.id')
                    ->whereColumn('annual.association_id', 'a.id')
                    ->whereColumn('annual.year', 'm.year')
                    ->selectRaw('
                        CASE
                            WHEN COUNT(*) = COUNT(*) FILTER (
                                WHERE annual.output_unit_code = p.production_unit_code
                                AND annual.output_unit_spec
                                    IS NOT DISTINCT FROM p.production_unit_spec
                            )
                            THEN SUM(annual.actual_output)
                            ELSE NULL
                        END
                    ');
            }, 'year_actual_total');
        }

        return $query;
    }

    public function save(
        string $type,
        array $data,
        User $actor,
        ?int $id = null
    ): int {
        $this->authorize($actor);
        abort_unless(isset(self::TYPES[$type]), 404);

        // The reason belongs to audit history, not the monitoring table.
        $reason = $data['correction_reason'] ?? null;

        if (is_string($reason)) {
            $reason = trim($reason);
        }

        $data = $this->validateInput($type, $data, $id !== null);

        // Record changes and audit history must succeed or roll back together.
        return DB::transaction(function () use (
            $type,
            $data,
            $actor,
            $id,
            $reason
        ): int {
            $actor = User::with('role')->sharedLock()->findOrFail($actor->id);
            $this->authorize($actor);

            // Lock the project and association against reassignment during saving.
            // This also serializes duplicate-period checks for the project.
            $project = $this->projects($actor)
                ->where('p.id', $data['project_id'])
                ->select('p.*', 'a.is_archived as association_archived')
                ->lockForUpdate()
                ->first();

            abort_unless($project, 404);

            if ($project->is_archived || $project->association_archived) {
                $this->invalid(
                    'project_id',
                    'Archived projects and associations retain read-only monitoring history.'
                );
            }

            $before = null;

            if ($id !== null) {
                $record = $this->records($type, $actor)
                    ->where('m.id', $id)
                    ->first();

                abort_unless($record, 404);

                if ((int) $record->project_id !== (int) $project->id) {
                    $this->invalid(
                        'project_id',
                        'The project cannot change for an existing monitoring record.'
                    );
                }

                if (
                    $type === 'materials'
                    && (int) $record->project_material_id !== (int) $data['project_material_id']
                ) {
                    $this->invalid(
                        'project_material_id',
                        'The material cannot change for an existing monitoring record.'
                    );
                }

                // Capture only the stored row, not joined display fields.
                $original = DB::table('monitoring_'.$type)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($original, 404);
                $before = (array) $original;

                if ($type !== 'materials') {
                    // A correction updates values within the original reporting period.
                    // It must not move historical output or income to another period.
                    $period = $type === 'production' ? 'quarter_id' : 'month';

                    foreach (['year', $period] as $field) {
                        if ((int) $data[$field] !== (int) $before[$field]) {
                            $this->invalid(
                                $field,
                                'The reporting period cannot change during a correction.'
                            );
                        }
                    }
                }

                if (
                    $type === 'production'
                    && $before['output_unit_code'] !== null
                ) {
                    // Confirmed historical units cannot be relabeled without conversion.
                    if (
                        $data['output_unit_code'] !== $before['output_unit_code']
                        || $data['output_unit_spec'] !== $before['output_unit_spec']
                    ) {
                        $this->invalid(
                            'output_unit_code',
                            'The confirmed unit and specification cannot change during a correction.'
                        );
                    }
                }
            }

            if ($type === 'materials') {
                // Includes ownership, archive, duplicate-token and immutable-token checks.
                $this->prepareMaterial($data, (int) $project->id, $id);
            } else {
                // Derive ownership from the locked project, never browser input.
                $data['association_id'] = $project->association_id;

                $period = $type === 'production' ? 'quarter_id' : 'month';

                $duplicate = DB::table('monitoring_'.$type)
                    ->where('project_id', $project->id)
                    ->where('year', $data['year'])
                    ->where($period, $data[$period]);

                if ($id !== null) {
                    $duplicate->where('id', '<>', $id);
                }

                if ($duplicate->exists()) {
                    $this->invalid(
                        'project_id',
                        'This project already has a record for this period. Edit that record.'
                    );
                }

                if ($type === 'production') {
                    // Existing legacy rows without a unit still need individual confirmation.
                    // The selected unit must agree with the project's established unit.
                    $this->confirmProductionUnit($project, $data);
                }
            }

            if ($id !== null) {
                // Check scope and locked fields before accepting a correction reason.
                // Whitespace-only reasons are rejected, including direct service calls.
                validator(
                    ['correction_reason' => $reason],
                    ['correction_reason' => ['required', 'string', 'max:2000']],
                    [
                        'correction_reason.required' =>
                            'Explain why this record needs correction.',
                    ]
                )->validate();
            }

            $data['updated_at'] = now();
            $action = $id === null ? 'CREATE' : 'UPDATE';

            if ($id === null) {
                $id = (int) DB::table('monitoring_'.$type)->insertGetId([
                    ...$data,
                    'created_by' => $actor->id,
                    'created_at' => now(),
                ]);
            } else {
                // Validation excludes creator and other server-managed fields.
                DB::table('monitoring_'.$type)
                    ->where('id', $id)
                    ->update($data);
            }

            $after = (array) DB::table('monitoring_'.$type)
                ->where('id', $id)
                ->first();

            // Preserve the explanation, previous values and replacement values.
            // Actor and decision time are stored in the existing audit columns.
            DB::table('audit_logs')->insert([
                'user_id' => $actor->id,
                'action_type' => $action,
                'module' => 'Monitoring',
                'record_id' => $id,
                'details' => json_encode([
                    'type' => $type,
                    'project_id' => (int) $project->id,
                    'correction_reason' => $action === 'UPDATE' ? $reason : null,
                    'before' => $before,
                    'after' => $after,
                ], JSON_THROW_ON_ERROR),
                'performed_at' => now(),
            ]);

            return $id;
        }, 3);
    }

    private function prepareMaterial(array &$data, int $projectId, ?int $id): void
    {
        $material = DB::table('project_materials')
            ->where('id', $data['project_material_id'])
            ->where('project_id', $projectId)
            ->lockForUpdate()
            ->first();

        // Check the database record even when the form hides archived materials.
        if (!$material || $material->archived_at !== null) {
            $this->invalid(
                'project_material_id',
                'Choose an active material belonging to this project. '
                .'Archived history cannot be changed.'
            );
        }

        if ($id === null) {
            // A repeated creation request must not create a second observation.
            $alreadySubmitted = DB::table('monitoring_materials')
                ->where('submission_token', $data['submission_token'])
                ->exists();

            if ($alreadySubmitted) {
                $this->invalid(
                    'submission_token',
                    'This form was already submitted. '
                    .'Reload the records before creating another event.'
                );
            }
        } else {
            // Scope the original event to the already authorized material.
            $original = DB::table('monitoring_materials')
                ->where('id', $id)
                ->where('project_material_id', $material->id)
                ->lockForUpdate()
                ->first();

            abort_unless($original, 404);

            // Reject a replacement token instead of silently accepting the request.
            // Legacy records with a null token also retain their original identity.
            if (
                array_key_exists('submission_token', $data)
                && $data['submission_token'] !== $original->submission_token
            ) {
                $this->invalid(
                    'submission_token',
                    'The submission token cannot be changed during a correction.'
                );
            }

            // Never include the token in the database update.
            unset($data['submission_token']);
        }

        unset($data['project_id']);
    }

    private function confirmProductionUnit(object $project, array $data): void
    {
        $code = $data['output_unit_code'];
        $spec = $data['output_unit_spec'];

        if ($project->production_unit_code === null) {
            // Do not establish a unit that conflicts with already confirmed history.
            $conflict = DB::table('monitoring_production')
                ->where('project_id', $project->id)
                ->whereNotNull('output_unit_code')
                ->where(function ($query) use ($code, $spec): void {
                    $query->where('output_unit_code', '<>', $code)
                        ->orWhereRaw(
                            'output_unit_spec IS DISTINCT FROM CAST(? AS varchar)',
                            [$spec]
                        );
                })
                ->exists();

            if ($conflict) {
                $this->invalid(
                    'output_unit_code',
                    'Existing production records use another unit or specification. Review that history first.'
                );
            }

            DB::table('projects')->where('id', $project->id)->update([
                'production_unit_code' => $code,
                'production_unit_spec' => $spec,
            ]);

            // Older records remain unknown until individually corrected.
            return;
        }

        if (
            $project->production_unit_code !== $code
            || $project->production_unit_spec !== $spec
        ) {
            $this->invalid(
                'output_unit_code',
                'Use the project’s established production unit and specification.'
            );
        }
    }

    private function validateInput(string $type, array $data, bool $editing): array
    {
        $today = now('Asia/Manila')->toDateString();

        // Normalize UUID text before comparing it with the stored PostgreSQL value.
        if (isset($data['submission_token']) && is_string($data['submission_token'])) {
            $data['submission_token'] = strtolower(trim($data['submission_token']));
        }

        $rules = [
            'project_id' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        $rules += match ($type) {
            'production' => [
                'year' => ['required', 'integer', 'between:1900,2100'],
                'quarter_id' => ['required', 'integer', 'exists:quarters,id'],
                'target_output' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
                'actual_output' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
                'output_unit_code' => ['required', 'string', Rule::in(array_keys(self::UNITS))],
                'output_unit_spec' => ['nullable', 'string', 'max:100'],
            ],
            'income' => [
                'year' => ['required', 'integer', 'between:1900,2100'],
                'month' => ['required', 'integer', 'between:1,12'],
                'gross_income' => ['required', 'numeric', 'decimal:0,2', 'between:0,9999999999.99'],
            ],
            'materials' => [
                'project_material_id' => ['required', 'integer', 'min:1'],
                'condition_status_id' => [
                    'required',
                    'integer',
                    Rule::exists('statuses', 'id')
                        ->whereIn('status_name', self::CONDITIONS),
                ],
                'material_description' => ['nullable', 'string', 'max:255'],
                'scheduled_maintenance' => ['nullable', 'date_format:Y-m-d'],
                'actual_maintenance' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
                'observed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
                // Keep a supplied token so corrections can reject attempted replacement.
                'submission_token' => $editing
                    ? ['sometimes', 'required', 'uuid']
                    : ['required', 'uuid'],
            ],
        };

        // Only validated fields reach persistence; supplied ownership/reviewer fields are ignored.
        $validated = validator($data, $rules)->validate();

        if ($type === 'production') {
            $this->validateProduction($validated);
        }

        if ($type === 'income') {
            $now = now('Asia/Manila');

            if (
                (int) $validated['year'] > $now->year
                || (
                    (int) $validated['year'] === $now->year
                    && (int) $validated['month'] > $now->month
                )
            ) {
                $this->invalid('month', 'Income cannot be recorded for a future month.');
            }
        }

        return $validated;
    }

    private function validateProduction(array &$data): void
    {
        $quarterName = DB::table('quarters')
            ->where('id', $data['quarter_id'])
            ->value('quarter_name');

        // Resolve the quarter by its name, not an assumed database ID.
        $quarter = match (strtoupper(trim((string) $quarterName))) {
            'Q1' => 1,
            'Q2' => 2,
            'Q3' => 3,
            'Q4' => 4,
            default => null,
        };

        if ($quarter === null) {
            $this->invalid('quarter_id', 'Choose a configured quarter from Q1 through Q4.');
        }

        $now = now('Asia/Manila');
        $currentQuarter = (int) ceil($now->month / 3);
        $year = (int) $data['year'];

        // Completed quarters permit corrections; unfinished quarters cannot record actual output.
        if (
            $year > $now->year
            || ($year === $now->year && $quarter >= $currentQuarter)
        ) {
            $this->invalid('quarter_id', 'Choose a completed quarter.');
        }

        $spec = trim((string) ($data['output_unit_spec'] ?? ''));
        $data['output_unit_spec'] = $spec === '' ? null : $spec;

        if (
            in_array($data['output_unit_code'], self::PACKAGED_UNITS, true)
            && $data['output_unit_spec'] === null
        ) {
            $this->invalid(
                'output_unit_spec',
                'Describe the package size, such as 250 g per pack or 500 mL per bottle.'
            );
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}