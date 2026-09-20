<?php

// app/Services/AssociationManagementService.php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AssociationRuleException;
use App\Models\AreaUnit;
use App\Models\Association;
use App\Models\GisLocation;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\ProgramComponent;
use App\Models\Project;
use App\Models\Status;
use App\Models\SubUnit;
use App\Models\Training;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssociationManagementService
{
    public const REGISTER_CARDS = [
        'total' => 'Total Associations', 'active' => 'Active Associations',
        'inactive' => 'Inactive Associations', 'archived' => 'Archived Associations',
    ];

    public const DETAIL_CARDS = [
        'members' => 'Official members', 'applications' => 'Pending applications',
        'projects' => 'Projects', 'trainings' => 'Trainings',
        'gis' => 'GIS locations', 'published_gis' => 'Published GIS',
    ];

    /**
     * Return the administrator list with constrained eager loading and calculated counts.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->recordQuery()
            ->withCount([
                'members as members_count' => fn (Builder $query) => $query->where('is_archived', false),
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('associations.name', 'ilike', "%{$search}%")
                    ->orWhere('associations.address', 'ilike', "%{$search}%");
            });
        }

        $this->applyIntegerFilter($query, 'area_unit_id', $filters['area_unit_id'] ?? null);
        $this->applyIntegerFilter($query, 'sub_unit_id', $filters['sub_unit_id'] ?? null);
        $this->applyIntegerFilter($query, 'program_component_id', $filters['program_component_id'] ?? null);
        $this->applyIntegerFilter($query, 'field_officer_id', $filters['field_officer_id'] ?? null);
        $this->applyIntegerFilter($query, 'status_id', $filters['status_id'] ?? null);

        match ((string) ($filters['archive_state'] ?? 'current')) {
            'archived' => $query->where('associations.is_archived', true),
            'all' => null,
            default => $query->where('associations.is_archived', false),
        };

        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $query->orderByDesc('associations.name'),
            'date_joined_desc' => $query->orderByDesc('associations.date_joined')->orderBy('associations.name'),
            'date_joined_asc' => $query->orderBy('associations.date_joined')->orderBy('associations.name'),
            'created_desc' => $query->orderByDesc('associations.created_at'),
            'updated_desc' => $query->orderByDesc('associations.updated_at'),
            default => $query->orderBy('associations.name'),
        };

        return $query->orderBy('associations.id')->paginate((int) ($filters['per_page'] ?? 10))
            ->appends(array_diff_key($filters, array_flip(['summary', 'summary_page'])))
            ->through(fn (Association $row) => $this->hydrateRelations($row));
    }

    public function summaryRecords(string $key): LengthAwarePaginator
    {
        // Summary cards describe the entire register, independently of its filters.
        // Their predicates mirror summary(): archived Active records belong only to
        // Total/Archived, not to the current Active/Inactive cards.
        abort_unless(array_key_exists($key, self::REGISTER_CARDS), 404);
        $query = $this->recordQuery();
        if ($key === 'archived') {
            $query->where('associations.is_archived', true);
        } elseif ($key !== 'total') {
            $query->where('associations.is_archived', false)->where('st.status_name', ucfirst($key));
        }

        return $query->orderBy('associations.name')->orderBy('associations.id')
            ->paginate(10, ['*'], 'summary_page')->through(fn (Association $row) => $this->hydrateRelations($row));
    }

    public function relatedRecords(Association $association, string $key): LengthAwarePaginator
    {
        // Select display fields explicitly: private member data and review hashes
        // must never enter a card response. Paginate at the database, not in JS.
        [$model, $columns] = match ($key) {
            'members' => [Member::class, ['id', 'first_name', 'middle_name', 'last_name', 'role_in_assoc', 'date_registered']],
            'applications' => [MemberApplication::class, ['id', 'first_name', 'middle_name', 'last_name', 'created_at']],
            'projects' => [Project::class, ['id', 'title', 'implementation_date']],
            'trainings' => [Training::class, ['id', 'title', 'venue', 'date_conducted']],
            'gis', 'published_gis' => [GisLocation::class, ['id', 'location_name', 'latitude', 'longitude', 'is_published']],
            default => abort(404),
        };
        $query = $model::query()->select($columns)->where('association_id', $association->id);
        $this->applyRelatedScope($query, $key);

        return $query->orderBy('id')->paginate(10, ['*'], 'related_page');
    }

    private function applyRelatedScope(Builder $query, string $key): void
    {
        // Counts and drill-down records use this same rule, preventing mismatched
        // totals when records are archived, applications reviewed, or GIS published.
        if (in_array($key, ['members', 'projects', 'trainings'], true)) {
            $query->where('is_archived', false);
        } elseif ($key === 'applications') {
            $query->whereHas('status', fn (Builder $status) => $status->where('status_name', 'Pending'));
        } elseif ($key === 'published_gis') {
            $query->where('is_published', true);
        }
    }

    /**
     * @return array{total:int, active:int, inactive:int, archived:int}
     */
    public function summary(): array
    {
        $rows = DB::table('associations')
            ->leftJoin('statuses', 'statuses.id', '=', 'associations.status_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE associations.is_archived = FALSE AND statuses.status_name = 'Active') AS active")
            ->selectRaw("COUNT(*) FILTER (WHERE associations.is_archived = FALSE AND statuses.status_name = 'Inactive') AS inactive")
            ->selectRaw('COUNT(*) FILTER (WHERE associations.is_archived = TRUE) AS archived')
            ->first();

        return [
            'total' => (int) ($rows->total ?? 0),
            'active' => (int) ($rows->active ?? 0),
            'inactive' => (int) ($rows->inactive ?? 0),
            'archived' => (int) ($rows->archived ?? 0),
        ];
    }

    /**
     * @return array<string, Collection<int, object>>
     */
    public function formOptions(): array
    {
        $queries = [
            'municipalities' => DB::table('area_units')->orderBy('name')->select(['id', 'name', 'is_archived']),
            'barangays' => DB::table('sub_units')->orderBy('name')->select(['id', 'area_unit_id', 'name', 'is_archived']),
            'programComponents' => DB::table('program_components')->orderBy('name')->select(['id', 'name']),
            'fieldOfficers' => DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')
                ->where(fn ($q) => $q->where('roles.role_name', 'Field Officer')->orWhereExists(fn ($a) => $a->selectRaw('1')->from('associations')->whereColumn('associations.field_officer_id', 'users.id')))
                ->orderBy('users.name')->select(['users.id', 'users.name', 'users.email', 'users.is_active', 'roles.role_name']),
            'associationStatuses' => DB::table('statuses')->whereIn('status_name', ['Active', 'Inactive'])->orderBy('status_name')->select(['id', 'status_name']),
        ];
        $batch = DB::query();
        foreach ($queries as $key => $query) {
            $batch->selectSub(DB::query()->fromSub($query, 'options')->selectRaw("COALESCE(json_agg(row_to_json(options)), '[]'::json)"), $key);
        }
        $row = $batch->first();
        $options = [];
        foreach ($queries as $key => $_) {
            $options[$key] = collect(json_decode($row->$key, false, 512, JSON_THROW_ON_ERROR));
        }
        $options['filterMunicipalities'] = $options['municipalities'];
        $options['filterBarangays'] = $options['barangays'];
        $options['filterOfficers'] = $options['fieldOfficers'];
        $options['municipalities'] = $options['municipalities']->where('is_archived', false)->values();
        $options['barangays'] = $options['barangays']->where('is_archived', false)->values();
        $options['fieldOfficers'] = $options['fieldOfficers']->where('is_active', true)->where('role_name', 'Field Officer')->values();

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $actorId): Association
    {
        return app(AssociationDatabase::class)->run(function () use ($data, $actorId): Association {
            $this->validateAssignment($data);
            $association = Association::query()->create([
                ...$data,
                'representative_member_id' => null,
                'is_archived' => false,
            ]);

            $this->writeAudit(
                $actorId,
                'CREATE',
                $association->id,
                "Created association '{$association->name}'."
            );

            return $association->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Association $association, array $data, int $actorId): Association
    {
        return app(AssociationDatabase::class)->run(function () use ($association, $data, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                throw new AssociationRuleException('Archived associations must be restored before editing.');
            }

            $beforeOfficer = $locked->field_officer_id;
            $beforeRepresentative = $locked->representative_member_id;
            $beforeStatus = $locked->status_id;

            $this->validateAssignment($data, $locked->id);
            $locked->fill($data);
            // The request check can become stale while another request archives a member.
            // Recheck under the association lock, taking the member lock second.
            if ($locked->isDirty('representative_member_id') && $locked->representative_member_id !== null) {
                $eligible = Member::query()
                    ->whereKey($locked->representative_member_id)
                    ->where('association_id', $locked->id)
                    ->where('is_archived', false)->lockForUpdate()->first();
                if (! $eligible) {
                    throw new AssociationRuleException('Choose a current member of this association as representative.');
                }
                // A new appointment requires a newly provisioned secret; old terms grant no access.
                $eligible->forceFill(['review_passphrase_hash' => null])->save();
            }
            $locked->save();

            $changes = array_keys($locked->getChanges());
            $this->writeAudit(
                $actorId,
                'UPDATE',
                $locked->id,
                'Updated association master information: '.implode(', ', $changes).'.'
            );

            if ($beforeOfficer !== $locked->field_officer_id) {
                $this->writeAudit(
                    $actorId,
                    'ASSIGN_OFFICER',
                    $locked->id,
                    "Reassigned Field Officer from user {$beforeOfficer} to user {$locked->field_officer_id}."
                );
            }

            if ($beforeStatus !== $locked->status_id) {
                $this->writeAudit(
                    $actorId,
                    'STATUS_CHANGE',
                    $locked->id,
                    "Changed operational status from {$beforeStatus} to {$locked->status_id}."
                );
            }

            if ($beforeRepresentative !== $locked->representative_member_id) {
                $this->auditRepresentativeChange(
                    $actorId,
                    $locked->id,
                    $beforeRepresentative,
                    $locked->representative_member_id
                );
            }

            return $locked->fresh();
        });
    }

    public function archive(Association $association, int $actorId): Association
    {
        return app(AssociationDatabase::class)->run(function () use ($association, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                return $locked;
            }

            $locked->forceFill(['is_archived' => true])->save();

            // GIS and audit infrastructure are required: failures must roll back archival.

            DB::table('gis_locations')
                ->where('association_id', $locked->id)
                ->where('is_published', true)
                ->update([
                    'is_published' => false,
                    'updated_at' => now(),
                ]);

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $locked->id,
                "Archived association '{$locked->name}' and unpublished its GIS locations."
            );

            return $locked->fresh();
        });
    }

    public function restore(Association $association, int $actorId): Association
    {
        return app(AssociationDatabase::class)->run(function () use ($association, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()
                ->with(['areaUnit:id,is_archived', 'subUnit:id,area_unit_id,is_archived'])
                ->lockForUpdate()
                ->findOrFail($association->id);

            if (! $locked->is_archived) {
                return $locked;
            }

            $this->requireOfficer((int) $locked->field_officer_id);

            if (! $locked->areaUnit || ! $locked->subUnit || $locked->areaUnit?->is_archived || $locked->subUnit?->is_archived) {
                throw new AssociationRuleException(
                    'The association cannot be restored while its municipality or barangay is archived.'
                );
            }

            if ((int) $locked->subUnit?->area_unit_id !== (int) $locked->area_unit_id) {
                throw new AssociationRuleException(
                    'The association cannot be restored because its barangay no longer belongs to its municipality.'
                );
            }

            if ($locked->representative_member_id !== null) {
                $validRepresentative = Member::query()
                    ->whereKey($locked->representative_member_id)
                    ->where('association_id', $locked->id)
                    ->where('is_archived', false)
                    ->exists();

                if (! $validRepresentative) {
                    $locked->representative_member_id = null;
                }
            }

            $locked->is_archived = false;
            $locked->save();

            $this->writeAudit(
                $actorId,
                'RESTORE',
                $locked->id,
                "Restored association '{$locked->name}'. GIS locations remain unpublished."
            );

            return $locked->fresh();
        });
    }

    public function assignRepresentative(
        Association $association,
        ?int $representativeMemberId,
        int $actorId
    ): Association {
        return app(AssociationDatabase::class)->run(function () use (
            $association,
            $representativeMemberId,
            $actorId
        ): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                throw new AssociationRuleException('Restore the association before changing its representative.');
            }

            $previous = $locked->representative_member_id;
            if ($previous === $representativeMemberId) {
                return $locked;
            }
            $locked->representative_member_id = $representativeMemberId;
            // The request check can become stale while another request archives a member.
            // Recheck under the association lock, taking the member lock second.
            if ($locked->isDirty('representative_member_id') && $locked->representative_member_id !== null) {
                $eligible = Member::query()
                    ->whereKey($locked->representative_member_id)
                    ->where('association_id', $locked->id)
                    ->where('is_archived', false)->lockForUpdate()->first();
                if (! $eligible) {
                    throw new AssociationRuleException('Choose a current member of this association as representative.');
                }
                // A new appointment requires a newly provisioned secret; old terms grant no access.
                $eligible->forceFill(['review_passphrase_hash' => null])->save();
            }
            $locked->save();

            $this->auditRepresentativeChange(
                $actorId,
                $locked->id,
                $previous,
                $representativeMemberId
            );

            return $locked->fresh();
        });
    }

    public function findDetailed(Association $association): Association
    {
        $record = $this->recordQuery()->where('associations.id', $association->id)->withCount([
            'members as members_count' => fn (Builder $query) => $this->applyRelatedScope($query, 'members'),
            'memberApplications as pending_applications_count' => fn (Builder $query) => $this->applyRelatedScope($query, 'applications'),
            'projects as projects_count' => fn (Builder $query) => $this->applyRelatedScope($query, 'projects'),
            'trainings as trainings_count' => fn (Builder $query) => $this->applyRelatedScope($query, 'trainings'),
            'gisLocations as gis_locations_count' => fn (Builder $query) => $this->applyRelatedScope($query, 'gis'),
            'gisLocations as published_gis_locations_count' => fn (Builder $query) => $this->applyRelatedScope($query, 'published_gis'),
        ])->firstOrFail();

        return $this->hydrateRelations($record);
    }

    /**
     * @return Collection<int, Member>
     */
    public function eligibleRepresentatives(Association $association): Collection
    {
        return Member::query()
            ->where('association_id', $association->id)
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'association_id',
                'first_name',
                'middle_name',
                'last_name',
                'role_in_assoc',
            ]);
    }

    /** Lock the user before checking eligibility; account changes use this same row lock. */
    private function requireOfficer(int $id): void
    {
        $officer = DB::table('users')->where('id', $id)->lockForUpdate()->first(['id', 'role_id', 'is_active']);
        $validRole = $officer && DB::table('roles')->where('id', $officer->role_id)->where('role_name', 'Field Officer')->exists();
        if (! $officer || ! $officer->is_active || ! $validRole) {
            throw ValidationException::withMessages(['field_officer_id' => 'Select an active Field Officer. Reassign the association if its former officer is unavailable.']);
        }
    }

    private function validateAssignment(array $data, ?int $ignoreId = null): void
    {
        $this->requireOfficer((int) $data['field_officer_id']);
        // The composite FK protects geography; these checks add understandable validation messages.
        $checks = DB::query()
            ->selectSub(DB::table('area_units')->where('id', $data['area_unit_id'])->where('is_archived', false)->selectRaw('COUNT(*)'), 'area')
            ->selectSub(DB::table('sub_units')->where('id', $data['sub_unit_id'])->where('area_unit_id', $data['area_unit_id'])->where('is_archived', false)->selectRaw('COUNT(*)'), 'barangay')
            ->selectSub(DB::table('program_components')->where('id', $data['program_component_id'])->selectRaw('COUNT(*)'), 'program')
            ->selectSub(DB::table('statuses')->where('id', $data['status_id'])->whereIn('status_name', ['Active', 'Inactive'])->selectRaw('COUNT(*)'), 'status')
            ->selectSub(DB::table('associations')->where('area_unit_id', $data['area_unit_id'])->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
                ->whereRaw("LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')) = LOWER(REGEXP_REPLACE(BTRIM(?), '\s+', ' ', 'g'))", [$data['name']])->selectRaw('COUNT(*)'), 'duplicate')->first();
        $errors = [];
        foreach (['area' => ['area_unit_id', 'Select a current municipality.'], 'barangay' => ['sub_unit_id', 'Select a current barangay belonging to this municipality.'], 'program' => ['program_component_id', 'Select a valid program component.'], 'status' => ['status_id', 'Association status must be Active or Inactive.']] as $check => [$field, $message]) {
            if (! $checks->$check) {
                $errors[$field] = $message;
            }
        }
        if ($checks->duplicate) {
            $errors['name'] = 'An association with this name already exists in the selected municipality.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function recordQuery(): Builder
    {
        // One joined round trip replaces six eager-loading requests to the remote database.
        // Explicit JSON fields prevent user passwords and member private data entering the page.
        return Association::query()->select('associations.*')
            ->leftJoin('area_units as au', 'au.id', '=', 'associations.area_unit_id')
            ->leftJoin('sub_units as su', 'su.id', '=', 'associations.sub_unit_id')
            ->leftJoin('program_components as pc', 'pc.id', '=', 'associations.program_component_id')
            ->leftJoin('users as fo', 'fo.id', '=', 'associations.field_officer_id')
            ->leftJoin('members as rep', 'rep.id', '=', 'associations.representative_member_id')
            ->leftJoin('statuses as st', 'st.id', '=', 'associations.status_id')
            ->selectRaw("json_build_object('id', au.id, 'name', au.name) AS area_json,
                json_build_object('id', su.id, 'name', su.name) AS sub_json,
                json_build_object('id', pc.id, 'name', pc.name) AS program_json,
                json_build_object('id', fo.id, 'name', fo.name, 'email', fo.email) AS officer_json,
                json_build_object('id', rep.id, 'first_name', rep.first_name, 'middle_name', rep.middle_name, 'last_name', rep.last_name, 'role_in_assoc', rep.role_in_assoc) AS representative_json,
                json_build_object('id', st.id, 'status_name', st.status_name) AS status_json");
    }

    private function hydrateRelations(Association $row): Association
    {
        foreach (['area_json' => ['areaUnit', AreaUnit::class], 'sub_json' => ['subUnit', SubUnit::class], 'program_json' => ['programComponent', ProgramComponent::class], 'officer_json' => ['fieldOfficer', User::class], 'representative_json' => ['representative', Member::class], 'status_json' => ['status', Status::class]] as $column => [$relation, $model]) {
            $attributes = json_decode($row->getAttribute($column), true, 512, JSON_THROW_ON_ERROR);
            $row->setRelation($relation, $attributes['id'] === null ? null : (new $model)->newFromBuilder($attributes));
            $row->offsetUnset($column);
        }

        return $row;
    }

    private function applyIntegerFilter(Builder $query, string $column, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            $query->where('associations.'.$column, (int) $value);
        }
    }

    private function auditRepresentativeChange(
        int $actorId,
        int $associationId,
        ?int $previous,
        ?int $current
    ): void {
        $action = match (true) {
            $previous === null && $current !== null => 'ASSIGN_REPRESENTATIVE',
            $previous !== null && $current === null => 'REMOVE_REPRESENTATIVE',
            default => 'CHANGE_REPRESENTATIVE',
        };

        $this->writeAudit(
            $actorId,
            $action,
            $associationId,
            'Representative changed from '.($previous ?? 'none').' to '.($current ?? 'none').'.'
        );
    }

    private function writeAudit(
        int $actorId,
        string $action,
        int $associationId,
        string $details
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $action,
            'module' => 'Association',
            'record_id' => $associationId,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}
