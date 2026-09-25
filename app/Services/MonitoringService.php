<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MonitoringService
{
    public const TYPES = ['production' => 'Production', 'income' => 'Income', 'materials' => 'Materials'];

    public const CONDITIONS = ['Good', 'Damaged', 'For Repair'];

    public function authorize(User $actor): void
    {
        abort_unless($actor->is_active && in_array($actor->role?->role_name, ['System Administrator', 'Field Officer'], true), 403);
    }

    public function projects(User $actor): Builder
    {
        $this->authorize($actor);
        $query = DB::table('projects as p')->join('associations as a', 'a.id', '=', 'p.association_id');
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
            $query->join('project_materials as pm', 'pm.project_id', '=', 'p.id')
                ->join('monitoring_materials as m', 'm.project_material_id', '=', 'pm.id')
                ->leftJoin('statuses as s', 's.id', '=', 'm.condition_status_id');
        } else {
            $query->join('monitoring_'.$type.' as m', function ($join): void {
                $join->on('m.project_id', '=', 'p.id')->on('m.association_id', '=', 'a.id');
            });
            if ($type === 'production') {
                $query->join('quarters as q', 'q.id', '=', 'm.quarter_id');
            }
        }
        $query->select('m.*', 'p.id as project_id', 'p.title as project_title', 'a.name as association_name', 'p.is_archived as project_archived', 'a.is_archived as association_archived');
        if ($type === 'materials') {
            $query->addSelect('pm.item_name', 's.status_name');
        } elseif ($type === 'production') {
            $query->addSelect('q.quarter_name');
        }

        return $query;
    }

    public function save(string $type, array $data, User $actor, ?int $id = null): int
    {
        $this->authorize($actor);
        abort_unless(isset(self::TYPES[$type]), 404);

        return DB::transaction(function () use ($type, $data, $actor, $id): int {
            // Serialize saves for a project so simultaneous submissions cannot create duplicate periods.
            $project = $this->projects($actor)->where('p.id', $data['project_id'])
                ->select('p.*', 'a.is_archived as association_archived')->lockForUpdate()->first();
            abort_unless($project, 404);
            if ($project->is_archived || $project->association_archived) {
                throw ValidationException::withMessages(['project_id' => 'Restore the project and association before changing monitoring records.']);
            }
            if ($id !== null) {
                $record = $this->records($type, $actor)->where('m.id', $id)->first();
                abort_unless($record, 404);
                if ((int) $record->project_id !== (int) $project->id) {
                    throw ValidationException::withMessages(['project_id' => 'The project cannot be changed for an existing record.']);
                }
            }
            $table = DB::table('monitoring_'.$type);
            if ($type === 'materials') {
                $material = DB::table('project_materials')->where('id', $data['project_material_id'])
                    ->where('project_id', $project->id)->lockForUpdate()->first();
                if (! $material) {
                    throw ValidationException::withMessages(['project_material_id' => 'Choose a material belonging to this project.']);
                }
                $duplicate = (clone $table)->where('project_material_id', $material->id);
                unset($data['project_id']);
            } else {
                $data['association_id'] = $project->association_id;
                $period = $type === 'production' ? 'quarter_id' : 'month';
                $duplicate = (clone $table)->where('project_id', $project->id)->where('year', $data['year'])->where($period, $data[$period]);
            }
            if ($id !== null) {
                $duplicate->where('id', '<>', $id);
            }
            if ($duplicate->exists()) {
                throw ValidationException::withMessages(['project_id' => $type === 'materials'
                    ? 'This material already has a monitoring record. Edit the existing record.'
                    : 'This project already has a record for this period. Edit the existing record.']);
            }
            $data['updated_at'] = now();
            if ($id === null) {
                $id = (int) $table->insertGetId([...$data, 'created_by' => $actor->id, 'created_at' => now()]);
                $action = 'CREATE';
            } else {
                $table->where('id', $id)->update($data);
                $action = 'UPDATE';
            }
            // A failed audit must also roll back the monitoring change.
            DB::table('audit_logs')->insert([
                'user_id' => $actor->id, 'action_type' => $action, 'module' => 'Monitoring',
                'record_id' => $id, 'details' => self::TYPES[$type].' monitoring for project #'.$project->id.'.',
                'performed_at' => now(),
            ]);

            return $id;
        }, 3);
    }
}
