<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** All tables and migrations are inside the existing rollback-only test schema. */
abstract class AssociationTerminationDatabaseTestCase extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_15_000001_add_association_assistance_status.php'))->up();
        DB::unprepared(<<<'SQL'
CREATE TABLE project_materials (id bigserial PRIMARY KEY, project_id bigint REFERENCES projects(id));
CREATE TABLE training_participants (id bigserial PRIMARY KEY, training_id bigint REFERENCES trainings(id), member_id bigint REFERENCES members(id), attendance_status_id bigint REFERENCES statuses(id));
CREATE TABLE monitoring_production (id bigserial PRIMARY KEY, association_id bigint REFERENCES associations(id), project_id bigint REFERENCES projects(id));
CREATE TABLE monitoring_income (id bigserial PRIMARY KEY, association_id bigint REFERENCES associations(id), project_id bigint REFERENCES projects(id));
CREATE TABLE monitoring_materials (id bigserial PRIMARY KEY, project_material_id bigint REFERENCES project_materials(id));
CREATE TABLE production_progress_entries (id bigserial PRIMARY KEY, monitoring_production_id bigint REFERENCES monitoring_production(id));
SQL);
        (require database_path('migrations/2026_10_16_000001_lock_terminated_associations.php'))->up();
    }
}
