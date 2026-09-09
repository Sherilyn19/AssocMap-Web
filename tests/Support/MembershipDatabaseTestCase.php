<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL behavior needs PostgreSQL tests, not SQLite imitations.
 * Opt-in creates an isolated schema INSIDE a transaction and always rolls it back.
 * No public tables are read, truncated, migrated or seeded by these tests.
 */
abstract class MembershipDatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ASSOCMAP_MEMBERSHIP_TESTS') !== '1') {
            $this->markTestSkipped('Set ASSOCMAP_MEMBERSHIP_TESTS=1 to run isolated PostgreSQL membership tests.');
        }
        $schema = 'assocmap_test_membership_'.bin2hex(random_bytes(8));
        config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');
        DB::beginTransaction();
        // Random server-generated identifier; never interpolate a user-supplied schema name.
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::statement('SET LOCAL search_path TO "'.$schema.'"');
        DB::statement("SET LOCAL statement_timeout = '15000ms'");
        DB::unprepared(<<<'SQL'
            CREATE TABLE roles (id bigserial PRIMARY KEY, role_name varchar NOT NULL);
            CREATE TABLE users (id bigserial PRIMARY KEY, name varchar NOT NULL, email varchar NOT NULL, password varchar, role_id bigint REFERENCES roles(id), association_id bigint, is_active boolean DEFAULT true, created_at timestamp, updated_at timestamp);
            CREATE TABLE statuses (id bigserial PRIMARY KEY, status_name varchar NOT NULL);
            CREATE TABLE sex (id bigserial PRIMARY KEY, sex_name varchar NOT NULL);
            CREATE TABLE area_units (id bigserial PRIMARY KEY, name varchar, is_archived boolean DEFAULT false);
            CREATE TABLE sub_units (id bigserial PRIMARY KEY, area_unit_id bigint, name varchar, is_archived boolean DEFAULT false);
            CREATE TABLE associations (id bigserial PRIMARY KEY, name varchar, field_officer_id bigint REFERENCES users(id), representative_member_id bigint, area_unit_id bigint, sub_unit_id bigint, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);
            CREATE TABLE member_applications (id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), first_name varchar NOT NULL, middle_name varchar, last_name varchar NOT NULL, birthday date NOT NULL, sex_id bigint REFERENCES sex(id), beneficiary_type varchar, contact_number varchar, address text, status_id bigint NOT NULL REFERENCES statuses(id), reviewed_by_member_id bigint, reviewed_at timestamp, rejection_reason text, created_at timestamp, updated_at timestamp);
            CREATE TABLE members (id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), application_id bigint REFERENCES member_applications(id), user_id bigint REFERENCES users(id), first_name varchar NOT NULL, middle_name varchar, last_name varchar NOT NULL, birthday date NOT NULL, sex_id bigint REFERENCES sex(id), role_in_assoc varchar, beneficiary_type varchar, contact_number varchar, address text, date_registered date NOT NULL, is_archived boolean DEFAULT false, created_at timestamp, updated_at timestamp);
            ALTER TABLE associations ADD FOREIGN KEY (representative_member_id) REFERENCES members(id);
            ALTER TABLE member_applications ADD FOREIGN KEY (reviewed_by_member_id) REFERENCES members(id);
            CREATE TABLE audit_logs (id bigserial PRIMARY KEY, user_id bigint REFERENCES users(id), action_type varchar, module varchar, record_id bigint, details text, performed_at timestamp);
            INSERT INTO roles (role_name) VALUES ('System Administrator'), ('Field Officer'), ('Association Member');
            INSERT INTO statuses (status_name) VALUES ('Pending'), ('Approved'), ('Rejected');
            INSERT INTO sex (sex_name) VALUES ('Female'), ('Male');
            INSERT INTO users (name,email,role_id,association_id) VALUES ('Admin','admin@example.test',1,NULL), ('Officer','officer@example.test',2,NULL), ('Association','association@example.test',3,1);
            INSERT INTO associations (name,field_officer_id) VALUES ('Assigned Association',2), ('Other Association',1);
            INSERT INTO members (association_id,first_name,last_name,birthday,sex_id,date_registered) VALUES (1,'Representative','One','1980-01-01',1,'2020-01-01'), (2,'Other','Person','1980-01-01',2,'2020-01-01');
            UPDATE associations SET representative_member_id=1 WHERE id=1;
        SQL);
        (require base_path('database/migrations/2026_08_08_083300_harden_member_management_integrity.php'))->up();
        (require base_path('database/migrations/2026_09_08_000001_add_member_review_passphrase.php'))->up();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        try {
            // Roll back the outer transaction even when an assertion/service throws.
            if ($this->app && getenv('ASSOCMAP_MEMBERSHIP_TESTS') === '1') {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                DB::disconnect('pgsql');
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function sessionFor(int $id, string $cachedRole): array
    {
        return ['auth_user' => ['id' => $id, 'role_name' => $cachedRole]];
    }
}
