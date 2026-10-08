<?php

declare(strict_types=1);

namespace Tests\Feature\FieldOfficerUser;

use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class ProjectBudgetTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tables exist only inside the base test's isolated transaction.
        DB::unprepared(<<<'SQL'
            CREATE TABLE program_components (
                id bigserial PRIMARY KEY,
                name varchar NOT NULL
            );

            CREATE TABLE projects (
                id bigserial PRIMARY KEY,
                association_id bigint REFERENCES associations(id),
                title varchar,
                commodity_type varchar,
                program_component_id bigint REFERENCES program_components(id),
                implementation_date date,
                terminated_on date,
                budget numeric(14,2),
                status_id bigint REFERENCES statuses(id),
                remarks text,
                is_archived boolean DEFAULT false,
                created_at timestamp,
                updated_at timestamp
            );

            INSERT INTO program_components (name) VALUES ('Aquaculture');
            INSERT INTO statuses (status_name) VALUES ('Planned');
        SQL);
    }

    public function test_officer_budget_validation_persistence_and_scope(): void
    {
        $this->withSession($this->sessionFor(2, 'Field Officer'));

        $data = [
            'association_id' => 1,
            'title' => 'Budget verification project',
            'commodity_type' => 'Fish',
            'program_component_id' => 1,
            'implementation_date' => '2025-01-01',
            'status_id' => DB::table('statuses')
                ->where('status_name', 'Planned')
                ->value('id'),
            'budget' => '12500.50',
        ];

        foreach (['-1', '1.001', '1000000000000'] as $invalidBudget) {
            $this->postJson('/officer/projects', [
                ...$data,
                'budget' => $invalidBudget,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('budget');
        }

        $this->assertSame(0, DB::table('projects')->count());

        // A valid amount is saved through the actual FO endpoint.
        $this->postJson('/officer/projects', $data)->assertOk();

        $id = (int) DB::table('projects')->value('id');

        $this->assertSame(
            '12500.50',
            DB::table('projects')->where('id', $id)->value('budget')
        );

        $update = $data;
        unset($update['association_id']);

        // An omitted field must preserve the previous recorded budget.
        unset($update['budget']);

        $this->putJson('/officer/projects/'.$id, $update)->assertOk();

        $this->assertSame(
            '12500.50',
            DB::table('projects')->where('id', $id)->value('budget')
        );

        // Zero and null have different meanings and must both be preserved.
        $this->putJson('/officer/projects/'.$id, [
            ...$update,
            'budget' => '0.00',
        ])->assertOk();

        $this->assertSame(
            '0.00',
            DB::table('projects')->where('id', $id)->value('budget')
        );

        $this->putJson('/officer/projects/'.$id, [
            ...$update,
            'budget' => null,
        ])->assertOk();

        $this->assertNull(
            DB::table('projects')->where('id', $id)->value('budget')
        );

        // Assignment remains enforced independently of the budget field.
        $this->postJson('/officer/projects', [
            ...$data,
            'association_id' => 2,
        ])->assertNotFound();

        DB::table('associations')->where('id', 1)
            ->update(['field_officer_id' => 1]);

        $this->putJson('/officer/projects/'.$id, [
            ...$update,
            'budget' => '900.00',
        ])->assertNotFound();

        $this->assertNull(
            DB::table('projects')->where('id', $id)->value('budget')
        );

        $this->assertSame(1, DB::table('projects')->count());
    }
}