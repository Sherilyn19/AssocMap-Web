<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Build domain tables before later integrity migrations run. */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('AssocMap requires PostgreSQL for its schema and integrity rules.');
        }
        // Existing manually created databases need reconciliation, not guessed repairs.
        // Stop before any writes if this is not a fresh installation.
        foreach ($this->tables() as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('Existing AssocMap tables require a reviewed baseline reconciliation before migration.');
            }
        }
        if (DB::table('users')->exists() || Schema::hasColumn('users', 'role_id')) {
            throw new RuntimeException('The fresh-install baseline requires an empty, unextended users table.');
        }
        // Laravel wraps PostgreSQL migrations in a transaction. Add circular foreign
        // keys only after both tables exist; later migrations add normalized indexes.
        DB::unprepared(<<<'SQL'
            CREATE TABLE roles (id bigserial PRIMARY KEY, role_name varchar(255) NOT NULL UNIQUE, created_at timestamp, updated_at timestamp);
            CREATE TABLE statuses (id bigserial PRIMARY KEY, status_name varchar(255) NOT NULL UNIQUE, created_at timestamp, updated_at timestamp);
            CREATE TABLE sex (id bigserial PRIMARY KEY, sex_name varchar(255) NOT NULL UNIQUE, created_at timestamp, updated_at timestamp);
            CREATE TABLE program_components (id bigserial PRIMARY KEY, name varchar(255) NOT NULL UNIQUE, created_at timestamp, updated_at timestamp);
            CREATE TABLE quarters (id bigserial PRIMARY KEY, quarter_name varchar(255) NOT NULL UNIQUE, created_at timestamp, updated_at timestamp);
            ALTER TABLE users ADD COLUMN role_id bigint NOT NULL REFERENCES roles(id), ADD COLUMN association_id bigint, ADD COLUMN is_active boolean NOT NULL DEFAULT true;
            CREATE INDEX users_role_active_index ON users(role_id, is_active);
            ALTER TABLE users ADD CONSTRAINT uq_users_association UNIQUE (association_id);
            CREATE TABLE area_units (id bigserial PRIMARY KEY, name varchar(255) NOT NULL, province varchar(255), address text, is_archived boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp);
            CREATE TABLE sub_units (id bigserial PRIMARY KEY, area_unit_id bigint NOT NULL REFERENCES area_units(id), name varchar(255) NOT NULL, is_archived boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp);
            CREATE TABLE associations (
                id bigserial PRIMARY KEY, name varchar(255) NOT NULL, area_unit_id bigint NOT NULL REFERENCES area_units(id), sub_unit_id bigint REFERENCES sub_units(id),
                program_component_id bigint REFERENCES program_components(id), field_officer_id bigint REFERENCES users(id), representative_member_id bigint,
                status_id bigint REFERENCES statuses(id), address text, date_joined date, is_archived boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp
            );
            ALTER TABLE users ADD CONSTRAINT users_association_id_foreign FOREIGN KEY (association_id) REFERENCES associations(id);
            CREATE TABLE member_applications (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), first_name varchar(255) NOT NULL, middle_name varchar(255), last_name varchar(255) NOT NULL,
                birthday date NOT NULL, sex_id bigint REFERENCES sex(id), beneficiary_type varchar(255), contact_number varchar(255), address text,
                status_id bigint NOT NULL REFERENCES statuses(id), reviewed_by_member_id bigint, reviewed_at timestamp, rejection_reason text, created_at timestamp, updated_at timestamp
            );
            CREATE TABLE members (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), application_id bigint REFERENCES member_applications(id), user_id bigint REFERENCES users(id),
                first_name varchar(255) NOT NULL, middle_name varchar(255), last_name varchar(255) NOT NULL, birthday date NOT NULL, sex_id bigint REFERENCES sex(id),
                role_in_assoc varchar(255), beneficiary_type varchar(255), contact_number varchar(255), address text, date_registered date NOT NULL,
                is_archived boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp
            );
            ALTER TABLE associations ADD CONSTRAINT associations_representative_member_id_foreign FOREIGN KEY (representative_member_id) REFERENCES members(id);
            ALTER TABLE member_applications ADD CONSTRAINT member_applications_reviewer_foreign FOREIGN KEY (reviewed_by_member_id) REFERENCES members(id);
            CREATE TABLE projects (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), title varchar(255) NOT NULL, commodity_type varchar(255),
                program_component_id bigint REFERENCES program_components(id), implementation_date date, budget numeric(14,2), status_id bigint REFERENCES statuses(id),
                remarks text, is_archived boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp
            );
            CREATE TABLE project_materials (
                id bigserial PRIMARY KEY, project_id bigint NOT NULL REFERENCES projects(id), item_name varchar(255) NOT NULL, quantity numeric(14,2) NOT NULL,
                unit varchar(255) NOT NULL, unit_cost numeric(14,2) NOT NULL, status_id bigint REFERENCES statuses(id), delivery_date date, created_at timestamp, updated_at timestamp
            );
            CREATE TABLE trainings (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), title varchar(255) NOT NULL, program_component_id bigint REFERENCES program_components(id),
                training_type varchar(255), venue varchar(255), date_conducted date, training_cost numeric(14,2), conducted_by varchar(255), remarks text,
                is_archived boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp
            );
            CREATE TABLE training_participants (id bigserial PRIMARY KEY, training_id bigint NOT NULL REFERENCES trainings(id), member_id bigint NOT NULL REFERENCES members(id), attendance_status_id bigint NOT NULL REFERENCES statuses(id), UNIQUE(training_id, member_id));
            CREATE TABLE monitoring_production (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), project_id bigint NOT NULL REFERENCES projects(id), quarter_id bigint NOT NULL REFERENCES quarters(id),
                year integer NOT NULL, target_output numeric(14,2) NOT NULL, actual_output numeric(14,2) NOT NULL, remarks text, created_by bigint REFERENCES users(id),
                created_at timestamp, updated_at timestamp, UNIQUE(project_id, quarter_id, year)
            );
            CREATE TABLE monitoring_income (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), project_id bigint NOT NULL REFERENCES projects(id), month integer NOT NULL,
                year integer NOT NULL, gross_income numeric(14,2) NOT NULL, remarks text, created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp, UNIQUE(project_id, month, year)
            );
            CREATE TABLE monitoring_materials (
                id bigserial PRIMARY KEY, project_material_id bigint NOT NULL REFERENCES project_materials(id), material_description varchar(255), condition_status_id bigint REFERENCES statuses(id),
                scheduled_maintenance date, actual_maintenance date, remarks text, created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp
            );
            CREATE TABLE gis_locations (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), location_name varchar(255), latitude numeric(10,8), longitude numeric(11,8),
                is_published boolean NOT NULL DEFAULT false, created_at timestamp, updated_at timestamp
            );
            CREATE TABLE audit_logs (id bigserial PRIMARY KEY, user_id bigint REFERENCES users(id), action_type varchar(255) NOT NULL, module varchar(255) NOT NULL, record_id bigint, details text, performed_at timestamp NOT NULL);
            CREATE INDEX audit_logs_user_action_time_index ON audit_logs(user_id, action_type, performed_at);
        SQL);
    }

    public function down(): void
    {
        // Remove only owned objects in dependency order. CASCADE could remove unrelated objects.
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_association_id_foreign');
        DB::statement('ALTER TABLE associations DROP CONSTRAINT associations_representative_member_id_foreign');
        DB::statement('ALTER TABLE member_applications DROP CONSTRAINT member_applications_reviewer_foreign');
        DB::statement('ALTER TABLE users DROP COLUMN role_id, DROP COLUMN association_id, DROP COLUMN is_active');
        foreach (array_reverse($this->tables()) as $table) {
            Schema::drop($table);
        }
    }

    private function tables(): array
    {
        return ['roles', 'statuses', 'sex', 'program_components', 'quarters', 'area_units', 'sub_units', 'associations', 'member_applications', 'members',
            'projects', 'project_materials', 'trainings', 'training_participants', 'monitoring_production', 'monitoring_income', 'monitoring_materials', 'gis_locations', 'audit_logs'];
    }
};
