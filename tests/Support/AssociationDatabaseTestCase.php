<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Extend the existing rollback-only fixture; never migrate or seed public tables. */
abstract class AssociationDatabaseTestCase extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            CREATE TABLE program_components (id bigserial PRIMARY KEY, name varchar);
            INSERT INTO program_components (name) VALUES ('SAAD');
            INSERT INTO statuses (status_name) VALUES ('Active'), ('Inactive');
            INSERT INTO area_units (name) VALUES ('Municipality A'), ('Municipality B');
            INSERT INTO sub_units (area_unit_id,name) VALUES (1,'Barangay A'),(2,'Barangay B');
            ALTER TABLE sub_units ADD UNIQUE (id,area_unit_id);
            ALTER TABLE associations ADD COLUMN program_component_id bigint REFERENCES program_components(id), ADD COLUMN status_id bigint REFERENCES statuses(id), ADD COLUMN address text, ADD COLUMN date_joined date;
            UPDATE associations SET area_unit_id=1,sub_unit_id=1,program_component_id=1,status_id=4,address='Fixture address',date_joined='2020-01-01',field_officer_id=2;
            ALTER TABLE associations ADD FOREIGN KEY (sub_unit_id,area_unit_id) REFERENCES sub_units(id,area_unit_id);
            CREATE UNIQUE INDEX associations_normalized_name_area_unique ON associations(area_unit_id,LOWER(REGEXP_REPLACE(BTRIM(name),'\s+',' ','g')));
            CREATE TABLE projects (id bigserial PRIMARY KEY, association_id bigint, is_archived boolean DEFAULT false);
            CREATE TABLE trainings (id bigserial PRIMARY KEY, association_id bigint, is_archived boolean DEFAULT false);
            CREATE TABLE gis_locations (id bigserial PRIMARY KEY, association_id bigint, is_published boolean, updated_at timestamp);
            INSERT INTO gis_locations (association_id,is_published) VALUES (1,true);
        SQL);
    }

    protected function payload(array $extra = []): array
    {
        return array_replace(['name' => 'New Association', 'area_unit_id' => 1, 'sub_unit_id' => 1, 'program_component_id' => 1, 'field_officer_id' => 2, 'status_id' => 4, 'address' => 'Test address', 'date_joined' => '2020-01-01'], $extra);
    }
}
