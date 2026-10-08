<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOfficerUser;

use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class MonitoringEvidenceTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // All fixtures remain inside the isolated test schema.
        DB::unprepared(<<<'SQL'
            CREATE TABLE projects (
                id bigserial PRIMARY KEY,
                association_id bigint REFERENCES associations(id),
                title varchar,
                status_id bigint,
                terminated_on date,
                is_archived boolean DEFAULT false
            );
            CREATE TABLE monitoring_income (
                id bigserial PRIMARY KEY,
                association_id bigint,
                project_id bigint,
                month integer,
                year integer,
                gross_income numeric(12,2),
                remarks text,
                created_at timestamp,
                updated_at timestamp
            );
            CREATE TABLE project_materials (
                id bigserial PRIMARY KEY,
                project_id bigint,
                item_name varchar,
                quantity numeric,
                unit varchar,
                delivery_date date,
                archived_at timestamp,
                status_id bigint
            );
            CREATE TABLE monitoring_materials (
                id bigserial PRIMARY KEY,
                project_material_id bigint,
                condition_status_id bigint,
                observed_on date,
                material_description varchar,
                scheduled_maintenance date,
                actual_maintenance date,
                remarks text,
                created_at timestamp,
                updated_at timestamp
            );

            INSERT INTO projects (association_id, title)
            VALUES (1, 'Assigned Project'), (2, 'Private Project');

            INSERT INTO monitoring_income
                (association_id, project_id, month, year, gross_income, remarks)
            VALUES
                (1, 1, 1, 2025, 100, 'January source'),
                (1, 1, 3, 2025, 150, 'March source'),
                (2, 2, 1, 2025, 900, 'Private income');

            INSERT INTO statuses (status_name) VALUES ('Good');

            INSERT INTO project_materials
                (project_id, item_name, quantity, unit, delivery_date, status_id)
            VALUES
                (1, 'Assigned Net', 2, 'pieces', '2025-01-01', 4),
                (2, 'Private Net', 1, 'piece', '2025-01-01', 4);

            INSERT INTO monitoring_materials
                (project_material_id, condition_status_id, observed_on, remarks)
            VALUES
                (1, 4, '2025-02-01', 'Assigned observation'),
                (1, 4, NULL, 'Undated legacy observation'),
                (2, 4, '2025-02-01', 'Private observation');
        SQL);
    }

    public function test_income_and_material_evidence_respect_current_assignment(): void
    {
        $this->withSession($this->sessionFor(2, 'Field Officer'));

        $this->get('/officer/monitoring/income/1/details')
            ->assertOk()
            ->assertSee('January source')
            ->assertSee('March source')
            ->assertSee('250.00')
            ->assertSee('unrecorded, not zero')
            ->assertDontSee('Private income');

        $this->get('/officer/monitoring/income/3/details')->assertNotFound();

        $this->get('/officer/monitoring/materials/1/details')
            ->assertOk()
            ->assertSee('Assigned observation')
            ->assertSee('Undated legacy observation')
            ->assertSee('2025-01-01')
            ->assertDontSee('Private observation');

        $this->get('/officer/monitoring/materials/3/details')->assertNotFound();

        // Reassignment immediately removes access to both evidence types.
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);

        $this->get('/officer/monitoring/income/1/details')->assertNotFound();
        $this->get('/officer/monitoring/materials/1/details')->assertNotFound();
    }

    public function test_association_account_cannot_open_officer_evidence(): void
    {
        // Sign in as an association account for this test.
        $this->withSession($this->sessionFor(3, 'Association Member'));

        // The existing middleware blocks access by redirecting this account
        // to its own dashboard and showing a permission error.
        foreach ([
            '/officer/monitoring/income/1/details',
            '/officer/monitoring/materials/1/details',
        ] as $url) {
            $this->get($url)
                ->assertRedirect(route('dashboard.member'))
                ->assertSessionHas(
                    'error',
                    'You do not have permission to access that page.'
                );
        }
    }
}