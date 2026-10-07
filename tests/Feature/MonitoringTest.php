<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\MembershipDatabaseTestCase;

final class MonitoringTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            CREATE TABLE projects (id bigserial PRIMARY KEY, association_id bigint REFERENCES associations(id), title varchar, status_id bigint REFERENCES statuses(id), terminated_on date, is_archived boolean DEFAULT false);
            CREATE TABLE project_materials (
                id bigserial PRIMARY KEY,
                project_id bigint REFERENCES projects(id),
                item_name varchar,
                archived_at timestamp
            );
            CREATE TABLE quarters (id bigserial PRIMARY KEY, quarter_name varchar);
            INSERT INTO quarters (quarter_name) VALUES ('Q1'),('Q2'),('Q3'),('Q4');
            INSERT INTO statuses (status_name) VALUES ('Good'),('Damaged'),('For Repair');
            INSERT INTO projects (association_id,title) VALUES (1,'Assigned Fish Project'),(2,'Private Other Project');
            INSERT INTO project_materials (project_id,item_name) VALUES (1,'Fishing Net'),(2,'Private Boat');
            CREATE TABLE monitoring_production (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), project_id bigint NOT NULL REFERENCES projects(id),
                quarter_id bigint NOT NULL REFERENCES quarters(id), year integer NOT NULL, target_output numeric(10,2) NOT NULL,
                actual_output numeric(10,2) NOT NULL, remarks text, created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp,
                UNIQUE(project_id,quarter_id,year)
            );
            CREATE TABLE monitoring_income (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id), project_id bigint NOT NULL REFERENCES projects(id),
                month integer NOT NULL, year integer NOT NULL, gross_income numeric(12,2) NOT NULL, remarks text,
                created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp, UNIQUE(project_id,month,year)
            );
            CREATE TABLE monitoring_materials (
                id bigserial PRIMARY KEY, project_material_id bigint NOT NULL REFERENCES project_materials(id), material_description varchar(255),
                condition_status_id bigint REFERENCES statuses(id), scheduled_maintenance date, actual_maintenance date, remarks text,
                created_by bigint REFERENCES users(id), created_at timestamp, updated_at timestamp
            );
        SQL);

        // Apply the workflow migration only within this test's isolated schema.
        (require base_path(
            'database/migrations/2026_10_06_000003_update_monitoring_workflow.php'
        ))->up();
    }

    private function production(array $extra = [
        'output_unit_code' => 'kg',
        'output_unit_spec' => null
    ]): array
    {
        return array_replace(['project_id' => 1, 'quarter_id' => 1, 'year' => 2025, 'target_output' => '100.00', 'actual_output' => '80.00', 'remarks' => 'Output in kilograms.', 'output_unit_code' => 'kg', 'output_unit_spec' => null], $extra);
    }

    public function test_authentication_and_current_role_are_enforced(): void
    {
        $this->get('/monitoring')->assertRedirect('/login');
        $this->withSession($this->sessionFor(3, 'System Administrator'));
        $this->get('/monitoring')->assertForbidden();
        $this->post('/monitoring/production', $this->production())->assertForbidden();
        $this->get('/monitoring/production/create')->assertForbidden();
        $this->assertSame(0, DB::table('monitoring_production')->count());
    }

    public function test_production_create_edit_duplicate_and_zero_target(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/monitoring/production/create')->assertOk()->assertSee('Target Output');
        $this->post('/monitoring/production', $this->production(['created_by' => 3, 'association_id' => 2]))->assertSessionHasNoErrors();
        $record = DB::table('monitoring_production')->first();
        $this->assertSame(1, $record->association_id);
        $this->assertSame(1, $record->created_by);
        $this->get('/monitoring')->assertOk()->assertSee('80.0%');
        $this->get('/monitoring/production/1/edit')->assertOk()->assertSee('Output in kilograms.');
        $this->postJson('/monitoring/production', $this->production())->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->put('/monitoring/production/1', $this->production([
            'target_output' => 0,
            'correction_reason' => 'Corrected the target using the source report.',
        ]))->assertSessionHasNoErrors();
        $this->get('/monitoring')->assertOk()->assertSee('N/A (zero target)');
        $this->assertSame(2, DB::table('audit_logs')->where('module', 'Monitoring')->count());
    }

    public function test_officers_cannot_read_or_modify_other_associations(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->post('/monitoring/production', $this->production(['project_id' => 2]))->assertSessionHasNoErrors();
        $this->withSession($this->sessionFor(2, 'System Administrator'));
        $this->get('/monitoring')->assertOk()->assertDontSee('Private Other Project');
        $this->get('/monitoring/materials/create')->assertOk()->assertDontSee('Private Boat');
        $this->get('/monitoring/production/1/edit')->assertNotFound();
        $this->postJson('/monitoring/production', $this->production(['project_id' => 2]))->assertNotFound();
        $this->putJson('/monitoring/production/1', $this->production())->assertNotFound();
        $this->post('/monitoring/production', $this->production())->assertSessionHasNoErrors();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        $this->putJson('/monitoring/production/2', $this->production())->assertNotFound();
        $this->get('/monitoring')->assertOk()->assertDontSee('Assigned Fish Project');
    }

    public function test_income_validation_and_monthly_duplicates(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $data = ['project_id' => 1, 'month' => 3, 'year' => 2025, 'gross_income' => '15000.25'];
        $this->get('/monitoring/income/create')->assertOk()->assertSee('Gross Income');
        $this->post('/monitoring/income', $data)->assertSessionHasNoErrors();
        $this->get('/monitoring?type=income')->assertOk()->assertSee('15,000.25')->assertSee('March');
        $this->postJson('/monitoring/income', $data)->assertUnprocessable();
        $this->put('/monitoring/income/1', [
            ...$data,
            'gross_income' => '0',
            'correction_reason' => 'Corrected income using the source ledger.',
        ])->assertSessionHasNoErrors();
        $this->postJson('/monitoring/income', [...$data, 'month' => 13, 'gross_income' => '-1'])->assertJsonValidationErrors(['month', 'gross_income']);
        $this->postJson('/monitoring/production', $this->production(['target_output' => '100000000', 'actual_output' => '1.001', 'quarter_id' => 999]))->assertJsonValidationErrors(['target_output', 'actual_output', 'quarter_id']);
        $this->travelTo(now('Asia/Manila')->setDate(2026, 1, 5));
        $this->postJson('/monitoring/income', [...$data, 'year' => 2026, 'month' => 2])->assertJsonValidationErrors('month');
        $this->travelBack();
    }

    public function test_income_details_show_support_and_separate_project_termination(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $status = DB::table('statuses')->insertGetId(['status_name' => 'Ongoing']);
        DB::table('projects')->where('id', 1)->update(['status_id' => $status]);
        $this->post('/monitoring/income', ['project_id' => 1, 'month' => 3, 'year' => 2025, 'gross_income' => '500', 'remarks' => '<script>unsafe</script>'])->assertSessionHasNoErrors();
        $this->get('/monitoring?type=income')->assertOk()->assertSee('Ongoing')->assertSee('data-income-details', false)
            ->assertSee('Close income details')->assertSee('Monitoring period')->assertSee('Last updated')
            ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertDontSee('<script>unsafe</script>', false);
        DB::table('projects')->where('id', 1)->update(['terminated_on' => '2025-04-01']);
        $this->get('/monitoring?type=income')->assertOk()->assertSee('Terminated')->assertSee('2025-04-01');
        $this->assertSame($status, DB::table('projects')->where('id', 1)->value('status_id'));
    }

    public function test_material_condition_ownership_maintenance_and_duplicates(): void
    {
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        $data = ['project_id' => 1, 'project_material_id' => 1, 'condition_status_id' => 5, 'scheduled_maintenance' => '2025-01-01', 'material_description' => 'Net needs repair.', 'observed_on' => '2025-01-01','submission_token' => (string) \Illuminate\Support\Str::uuid()];
        $this->post('/monitoring/materials', $data)->assertSessionHasNoErrors();
        $this->get('/monitoring?type=materials')->assertOk()->assertSee('Damaged')->assertSee('Maintenance overdue');
        $this->get('/monitoring/materials/1/edit')->assertOk()->assertSee('Net needs repair.');
        $this->postJson('/monitoring/materials', $data)->assertUnprocessable();
        $this->put('/monitoring/materials/1', [
            ...$data,
            'actual_maintenance' => '2025-01-02',
            'correction_reason' => 'Recorded the verified maintenance completion date.',
        ])->assertSessionHasNoErrors();
        $this->get('/monitoring?type=materials')->assertOk()->assertDontSee('Maintenance overdue');
        $this->postJson('/monitoring/materials', [...$data, 'project_material_id' => 2])->assertJsonValidationErrors('project_material_id');
        $this->postJson('/monitoring/materials', [...$data, 'condition_status_id' => 1])->assertJsonValidationErrors('condition_status_id');
        $this->putJson('/monitoring/materials/1', [...$data, 'actual_maintenance' => now('Asia/Manila')->addDay()->toDateString()])->assertJsonValidationErrors('actual_maintenance');
    }

    public function test_archived_projects_and_associations_retain_read_only_history(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->post('/monitoring/production', $this->production())->assertSessionHasNoErrors();
        DB::table('projects')->where('id', 1)->update(['is_archived' => true]);
        $this->get('/monitoring')->assertOk()->assertSee('Read only');
        $this->get('/monitoring/production/1/edit')->assertNotFound();
        $this->putJson('/monitoring/production/1', $this->production())->assertJsonValidationErrors('project_id');
        DB::table('projects')->where('id', 1)->update(['is_archived' => false]);
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->postJson('/monitoring/production', $this->production(['quarter_id' => 2]))->assertJsonValidationErrors('project_id');
        $this->assertSame(1, DB::table('monitoring_production')->count());
    }

    public function test_audit_failure_rolls_back_and_keeps_form_input_private(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_monitoring CHECK (module <> 'Monitoring')");
        $this->postJson('/monitoring/production', $this->production())->assertStatus(503)->assertDontSee('SQLSTATE');
        $this->from('/monitoring/production/create')->post('/monitoring/production', $this->production(['unexpected' => 'private']))
            ->assertRedirect('/monitoring/production/create')->assertSessionHas('_old_input.remarks', 'Output in kilograms.')->assertSessionMissing('_old_input.unexpected');
        $this->assertSame(0, DB::table('monitoring_production')->count());
    }

    public function test_filters_pagination_escaping_and_invalid_types(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        for ($year = 2010; $year < 2022; $year++) {
            $this->post('/monitoring/production', $this->production(['year' => $year, 'remarks' => '<script>alert(1)</script>']))->assertSessionHasNoErrors();
        }
        $this->get('/monitoring?page=2')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/monitoring?year=2000')->assertOk()->assertSee('No monitoring records found');
        $this->get('/monitoring?search=missing')->assertOk()->assertSee('No monitoring records found');
        $this->get('/monitoring?project_id=2')->assertOk()->assertSee('No monitoring records found');
        $this->getJson('/monitoring?type[]=production')->assertUnprocessable();
        $this->get('/monitoring/unknown/create')->assertNotFound();
        $this->putJson('/monitoring/production/1', $this->production(['project_id' => 2]))->assertJsonValidationErrors('project_id');
    }

    public function test_material_events_preserve_history_and_archived_materials_are_read_only(): void
        {
            $this->withSession($this->sessionFor(2, 'Field Officer'));

            $event = [
                'project_id' => 1,
                'project_material_id' => 1,
                'condition_status_id' => 4,
                'observed_on' => '2025-01-01',
                'submission_token' => (string) \Illuminate\Support\Str::uuid(),
            ];

            $this->post('/monitoring/materials', $event)->assertSessionHasNoErrors();

            $this->post('/monitoring/materials', [
                ...$event,
                'observed_on' => '2025-02-01',
                'condition_status_id' => 5,
                'submission_token' => (string) \Illuminate\Support\Str::uuid(),
            ])->assertSessionHasNoErrors();

            $this->assertSame(2, DB::table('monitoring_materials')->count());

            // Correcting one event must not replace the other event.
            $this->put('/monitoring/materials/1', [
                ...$event,
                'remarks' => 'Corrected inspection note.',
                'correction_reason' => 'Corrected the note against the inspection record.',
            ])->assertSessionHasNoErrors();

            $this->assertSame(
                '2025-02-01',
                DB::table('monitoring_materials')->where('id', 2)->value('observed_on')
            );

            $audit = DB::table('audit_logs')
                ->where('module', 'Monitoring')
                ->where('action_type', 'UPDATE')
                ->orderByDesc('id')->first();

            $details = json_decode($audit->details, true);
            $this->assertArrayHasKey('before', $details);
            $this->assertArrayHasKey('after', $details);

            DB::table('project_materials')->where('id', 1)
                ->update(['archived_at' => now()]);

            $this->putJson('/monitoring/materials/1', $event)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('project_material_id');

            $this->get('/monitoring?type=materials')
                ->assertOk()->assertSee('Read only');
        }

        public function test_production_requires_completed_quarters_and_fixed_units(): void
        {
            $this->withSession($this->sessionFor(2, 'Field Officer'));

            // On October 6, Q3 is complete but Q4 is still open.
            $this->travelTo(\Illuminate\Support\Carbon::parse(
                '2026-10-06 09:00:00',
                'Asia/Manila'
            ));

            try {
                $this->post('/monitoring/production', $this->production([
                    'year' => 2026,
                    'quarter_id' => 3,
                ]))->assertSessionHasNoErrors();

                $this->postJson('/monitoring/production', $this->production([
                    'year' => 2026,
                    'quarter_id' => 4,
                ]))->assertUnprocessable()->assertJsonValidationErrors('quarter_id');

                $this->postJson('/monitoring/production', $this->production([
                    'year' => 2026,
                    'quarter_id' => 2,
                    'output_unit_code' => 'mt',
                ]))->assertUnprocessable()->assertJsonValidationErrors('output_unit_code');

                $this->assertSame(
                    'kg',
                    DB::table('projects')->where('id', 1)->value('production_unit_code')
                );
            } finally {
                $this->travelBack();
            }
        }

        public function test_material_dates_and_tokens_are_validated_and_preserved(): void
        {
            // At this instant it is already October 7 in the Philippines,
            // although the UTC calendar date is still October 6.
            $this->travelTo(\Illuminate\Support\Carbon::parse(
                '2026-10-06 17:00:00',
                'UTC'
            ));

            try {
                foreach ([
                    1 => 'System Administrator',
                    2 => 'Field Officer',
                ] as $actorId => $role) {
                    $this->withSession($this->sessionFor($actorId, $role));

                    $token = (string) \Illuminate\Support\Str::uuid();
                    $data = [
                        'project_id' => 1,
                        'project_material_id' => 1,
                        'condition_status_id' => 4,
                        'observed_on' => '2026-10-07',
                        'submission_token' => $token,
                    ];

                    $countBefore = DB::table('monitoring_materials')->count();

                    $this->postJson('/monitoring/materials', [
                        ...$data,
                        'observed_on' => '2026-10-08',
                    ])->assertUnprocessable()
                        ->assertJsonValidationErrors('observed_on');

                    $this->postJson('/monitoring/materials', [
                        ...$data,
                        'actual_maintenance' => '2026-10-08',
                    ])->assertUnprocessable()
                        ->assertJsonValidationErrors('actual_maintenance');

                    $this->postJson('/monitoring/materials', [
                        ...$data,
                        'submission_token' => 'not-a-uuid',
                    ])->assertUnprocessable()
                        ->assertJsonValidationErrors('submission_token');

                    $withoutToken = $data;
                    unset($withoutToken['submission_token']);

                    $this->postJson('/monitoring/materials', $withoutToken)
                        ->assertUnprocessable()
                        ->assertJsonValidationErrors('submission_token');

                    $this->assertSame(
                        $countBefore,
                        DB::table('monitoring_materials')->count()
                    );

                    // Today's Philippine date is valid.
                    $this->post('/monitoring/materials', $data)
                        ->assertRedirect()
                        ->assertSessionHasNoErrors();

                    $id = (int) DB::table('monitoring_materials')
                        ->where('submission_token', $token)
                        ->value('id');

                    $this->assertGreaterThan(0, $id);

                    // Replaying a successful creation must not create another event.
                    $this->postJson('/monitoring/materials', $data)
                        ->assertUnprocessable()
                        ->assertJsonValidationErrors('submission_token');

                    $this->assertSame(
                        $countBefore + 1,
                        DB::table('monitoring_materials')->count()
                    );

                    $auditCount = DB::table('audit_logs')
                        ->where('module', 'Monitoring')
                        ->count();

                    // A valid but different UUID must also be rejected on correction.
                    $this->putJson('/monitoring/materials/'.$id, [
                        ...$data,
                        'submission_token' => (string) \Illuminate\Support\Str::uuid(),
                        'remarks' => 'This change must not be saved.',
                    ])->assertUnprocessable()
                        ->assertJsonValidationErrors('submission_token');

                    $this->assertSame(
                        $auditCount,
                        DB::table('audit_logs')->where('module', 'Monitoring')->count()
                    );

                    $this->assertNull(
                        DB::table('monitoring_materials')->where('id', $id)->value('remarks')
                    );

                    // The existing correction form omits the token; preserve it server-side.
                    $this->put('/monitoring/materials/'.$id, [
                        ...$withoutToken,
                        'remarks' => 'Inspection description corrected.',
                        'correction_reason' => 'Corrected the description against the inspection record.',
                    ])->assertRedirect()
                        ->assertSessionHasNoErrors();

                    $this->assertSame(
                        $token,
                        DB::table('monitoring_materials')
                            ->where('id', $id)
                            ->value('submission_token')
                    );

                    $this->assertSame(
                        'Inspection description corrected.',
                        DB::table('monitoring_materials')->where('id', $id)->value('remarks')
                    );
                }
            } finally {
                $this->travelBack();
            }
        }
        public function test_corrections_require_reasons_and_preserve_period_identity(): void
    {
        foreach ([
            1 => 'System Administrator',
            2 => 'Field Officer',
        ] as $actorId => $role) {
            $this->withSession($this->sessionFor($actorId, $role));

            // Different years prevent the two actors' fixtures from colliding.
            $year = 2020 + $actorId;

            $cases = [
                'production' => [
                    'data' => $this->production(['year' => $year]),
                    'field' => 'actual_output',
                    'before' => '80.00',
                    'after' => '85.00',
                    'locked' => ['year' => $year - 1, 'quarter_id' => 2],
                ],
                'income' => [
                    'data' => [
                        'project_id' => 1,
                        'year' => $year,
                        'month' => 1,
                        'gross_income' => '100.00',
                    ],
                    'field' => 'gross_income',
                    'before' => '100.00',
                    'after' => '125.00',
                    'locked' => ['year' => $year - 1, 'month' => 2],
                ],
                'materials' => [
                    'data' => [
                        'project_id' => 1,
                        'project_material_id' => 1,
                        'condition_status_id' => 4,
                        'observed_on' => '2020-01-01',
                        'submission_token' => (string) \Illuminate\Support\Str::uuid(),
                        'remarks' => 'Original inspection note.',
                    ],
                    'field' => 'remarks',
                    'before' => 'Original inspection note.',
                    'after' => 'Verified inspection note.',
                    'locked' => [],
                ],
            ];

            foreach ($cases as $type => $case) {
                $this->post('/monitoring/'.$type, $case['data'])
                    ->assertRedirect()
                    ->assertSessionHasNoErrors();

                $table = 'monitoring_'.$type;
                $id = (int) DB::table($table)->max('id');
                $url = '/monitoring/'.$type.'/'.$id;

                $correction = [
                    ...$case['data'],
                    $case['field'] => $case['after'],
                ];

                $auditCount = DB::table('audit_logs')
                    ->where('module', 'Monitoring')
                    ->count();

                // Neither missing nor whitespace-only reasons may change the record.
                $this->putJson($url, $correction)
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('correction_reason');

                $this->putJson($url, [
                    ...$correction,
                    'correction_reason' => '   ',
                ])->assertUnprocessable()
                    ->assertJsonValidationErrors('correction_reason');

                foreach ($case['locked'] as $field => $replacement) {
                    $this->putJson($url, [
                        ...$correction,
                        $field => $replacement,
                        'correction_reason' => 'Attempted period replacement.',
                    ])->assertUnprocessable()
                        ->assertJsonValidationErrors($field);
                }

                if ($type === 'production') {
                    $this->putJson($url, [
                        ...$correction,
                        'output_unit_code' => 'mt',
                        'correction_reason' => 'Attempted unit replacement.',
                    ])->assertUnprocessable()
                        ->assertJsonValidationErrors('output_unit_code');
                }

                $this->assertSame(
                    $case['before'],
                    (string) DB::table($table)
                        ->where('id', $id)
                        ->value($case['field'])
                );

                $this->assertSame(
                    $auditCount,
                    DB::table('audit_logs')->where('module', 'Monitoring')->count()
                );

                $reason = 'Corrected against the verified source document.';

                $this->put($url, [
                    ...$correction,
                    'correction_reason' => $reason,
                ])->assertRedirect()
                    ->assertSessionHasNoErrors();

                $audit = DB::table('audit_logs')
                    ->where('module', 'Monitoring')
                    ->where('action_type', 'UPDATE')
                    ->where('record_id', $id)
                    ->orderByDesc('id')
                    ->first();

                $this->assertNotNull($audit);

                $details = json_decode(
                    $audit->details,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $this->assertSame($type, $details['type']);
                $this->assertSame($reason, $details['correction_reason']);
                $this->assertSame(
                    $case['before'],
                    (string) $details['before'][$case['field']]
                );
                $this->assertSame(
                    $case['after'],
                    (string) $details['after'][$case['field']]
                );
                $this->assertSame($actorId, (int) $audit->user_id);
                $this->assertNotNull($audit->performed_at);
            }
        }
    }
}
