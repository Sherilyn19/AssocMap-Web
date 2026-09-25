<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Training;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\Support\MembershipDatabaseTestCase;

final class TrainingManagementTest extends MembershipDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Match the existing Supabase schema inside the rollback-only test schema.
        DB::unprepared(<<<'SQL'
            CREATE TABLE program_components (id bigserial PRIMARY KEY, name varchar);
            INSERT INTO program_components (name) VALUES ('Aquaculture');
            INSERT INTO statuses (status_name) VALUES ('Present'), ('Absent');
            CREATE TABLE trainings (
                id bigserial PRIMARY KEY, association_id bigint NOT NULL REFERENCES associations(id),
                title varchar(255) NOT NULL, program_component_id bigint REFERENCES program_components(id),
                training_type varchar(100), venue varchar(255), date_conducted date,
                training_cost numeric(10,2), conducted_by varchar(255), remarks text,
                is_archived boolean NOT NULL DEFAULT false, created_at timestamp DEFAULT now(), updated_at timestamp DEFAULT now()
            );
            CREATE TABLE training_participants (
                id bigserial PRIMARY KEY, training_id bigint NOT NULL REFERENCES trainings(id),
                member_id bigint NOT NULL REFERENCES members(id), attendance_status_id bigint NOT NULL REFERENCES statuses(id),
                UNIQUE(training_id,member_id)
            );
        SQL);
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'association_id' => 1, 'title' => 'Fish Handling Workshop', 'program_component_id' => 1,
            'training_type' => 'Skills Training', 'venue' => 'Municipal Hall', 'date_conducted' => '2025-03-10',
            'training_cost' => '25000.00', 'conducted_by' => 'BFAR Region VII', 'remarks' => 'Practical workshop.',
        ], $extra);
    }

    private function training(array $extra = []): Training
    {
        return Training::create($this->payload($extra));
    }

    public function test_only_current_administrators_can_access_training_routes(): void
    {
        $training = $this->training();
        $this->get('/admin/trainings')->assertRedirect('/login');
        foreach ([2 => 'dashboard.officer', 3 => 'dashboard.member'] as $userId => $dashboard) {
            $this->withSession($this->sessionFor($userId, 'System Administrator'));
            $this->get('/admin/trainings')->assertRedirect(route($dashboard));
            $this->post('/admin/trainings', $this->payload())->assertRedirect(route($dashboard));
            $this->patch('/admin/trainings/'.$training->id.'/archive')->assertRedirect(route($dashboard));
            $this->post('/admin/trainings/'.$training->id.'/participants', ['member_id' => 1])->assertRedirect(route($dashboard));
        }
        $this->assertSame(1, Training::count());
        $this->assertSame(0, DB::table('training_participants')->count());
    }

    public function test_create_edit_details_and_validation(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/admin/trainings/create')->assertOk()->assertSee('Training Cost');
        $this->post('/admin/trainings', $this->payload())->assertSessionHasNoErrors()->assertRedirect('/admin/trainings/1');
        $this->get('/admin/trainings/1')->assertOk()->assertSee('Fish Handling Workshop')->assertSee('Register a participant');
        $this->get('/admin/trainings/1/edit')->assertOk()->assertSee('25000.00');
        $this->put('/admin/trainings/1', $this->payload(['title' => 'Updated Workshop']))->assertSessionHasNoErrors()->assertRedirect('/admin/trainings/1');
        $this->assertSame('Updated Workshop', Training::findOrFail(1)->title);
        $this->postJson('/admin/trainings', $this->payload(['title' => ' ', 'training_cost' => '-1', 'date_conducted' => 'invalid', 'training_type' => str_repeat('a', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'training_cost', 'date_conducted', 'training_type']);
        $this->postJson('/admin/trainings', $this->payload(['training_cost' => '100000000', 'program_component_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors(['training_cost', 'program_component_id']);
        $this->assertSame(2, DB::table('audit_logs')->where('module', 'Training Management')->count());
    }

    public function test_register_attendance_remove_and_ownership_rules(): void
    {
        $training = $this->training();
        $other = $this->training(['association_id' => 2, 'title' => 'Other training']);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $url = '/admin/trainings/'.$training->id;
        $this->post($url.'/participants', ['member_id' => 1])->assertSessionHasNoErrors();
        $participant = DB::table('training_participants')->first();
        $this->assertSame(1, $participant->attendance_status_id);
        $this->postJson($url.'/participants', ['member_id' => 1])->assertUnprocessable()->assertJsonValidationErrors('member_id');
        $this->postJson($url.'/participants', ['member_id' => 2])->assertUnprocessable()->assertJsonValidationErrors('member_id');
        $this->patchJson($url.'/participants/'.$participant->id, ['attendance_status_id' => 2])->assertUnprocessable()->assertJsonValidationErrors('attendance_status_id');
        $this->patch('/admin/trainings/'.$other->id.'/participants/'.$participant->id, ['attendance_status_id' => 4])->assertNotFound();
        $this->delete('/admin/trainings/'.$other->id.'/participants/'.$participant->id)->assertNotFound();
        $this->patch($url.'/participants/'.$participant->id, ['attendance_status_id' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, DB::table('training_participants')->value('attendance_status_id'));
        $this->get($url)->assertOk()->assertSee('Representative')->assertSee('Present');
        $this->putJson($url, $this->payload(['association_id' => 2]))->assertUnprocessable()->assertJsonValidationErrors('association_id');
        $this->putJson($url, $this->payload(['date_conducted' => now('Asia/Manila')->addDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('date_conducted');
        $this->delete($url.'/participants/'.$participant->id)->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('training_participants')->count());
        $this->assertSame(3, DB::table('audit_logs')->where('module', 'Training Management')->count());
    }

    public function test_archive_restore_keeps_attendance_and_blocks_stale_writes(): void
    {
        $training = $this->training();
        $url = '/admin/trainings/'.$training->id;
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->post($url.'/participants', ['member_id' => 1]);
        $participant = DB::table('training_participants')->value('id');
        $this->patch($url.'/archive')->assertSessionHasNoErrors();
        $this->patch($url.'/archive')->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'ARCHIVE')->count());
        $this->get($url)->assertOk()->assertSee('Restore Training')->assertDontSee('Add Participant');
        $this->get($url.'/edit')->assertNotFound();
        $this->putJson($url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('training');
        $this->postJson($url.'/participants', ['member_id' => 1])->assertUnprocessable();
        $this->patchJson($url.'/participants/'.$participant, ['attendance_status_id' => 4])->assertUnprocessable();
        $this->deleteJson($url.'/participants/'.$participant)->assertUnprocessable();
        $this->assertSame(1, DB::table('training_participants')->count());
        $this->patch($url.'/restore')->assertSessionHasNoErrors();
        $this->assertFalse($training->fresh()->is_archived);
        $this->get($url)->assertOk()->assertSee('Register a participant');
    }

    public function test_future_training_and_archived_member_rules(): void
    {
        $training = $this->training(['date_conducted' => now('Asia/Manila')->addDay()->toDateString()]);
        $url = '/admin/trainings/'.$training->id;
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        DB::table('members')->where('id', 1)->update(['is_archived' => true]);
        $this->postJson($url.'/participants', ['member_id' => 1])->assertUnprocessable();
        DB::table('members')->where('id', 1)->update(['is_archived' => false]);
        $this->post($url.'/participants', ['member_id' => 1])->assertSessionHasNoErrors();
        $participant = DB::table('training_participants')->value('id');
        $this->patchJson($url.'/participants/'.$participant, ['attendance_status_id' => 4])->assertUnprocessable()->assertJsonValidationErrors('attendance_status_id');
        $this->assertSame(1, DB::table('training_participants')->value('attendance_status_id'));
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->putJson($url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('association_id');
        $this->postJson('/admin/trainings', $this->payload())->assertUnprocessable();
        $this->patch($url.'/archive')->assertSessionHasNoErrors();
        $this->patchJson($url.'/restore')->assertUnprocessable();
    }

    public function test_filters_pagination_and_escaped_output(): void
    {
        $this->training(['title' => 'Archived special', 'is_archived' => true]);
        $this->training(['title' => 'Future special', 'association_id' => 2, 'date_conducted' => now('Asia/Manila')->addDay()->toDateString()]);
        $this->training(['title' => '<script>alert(1)</script>']);
        for ($i = 0; $i < 11; $i++) {
            $this->training(['title' => 'Past workshop '.$i]);
        }
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/admin/trainings?search=special')->assertOk()->assertSee('Future special')->assertDontSee('Archived special');
        $this->get('/admin/trainings?scope=archived')->assertOk()->assertSee('Archived special')->assertDontSee('Future special');
        $this->get('/admin/trainings?association_id=1&period=upcoming')->assertOk()->assertSee('No trainings found');
        $this->get('/admin/trainings?period=past&page=2')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->getJson('/admin/trainings?scope[]=bad')->assertUnprocessable();
    }

    public function test_failed_audit_rolls_back_training_and_participant_changes(): void
    {
        $training = $this->training();
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_training_audit CHECK (module <> 'Training Management')");
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->postJson('/admin/trainings', $this->payload())->assertStatus(503)->assertDontSee('SQLSTATE');
        $this->postJson('/admin/trainings/'.$training->id.'/participants', ['member_id' => 1])->assertStatus(503);
        $this->patchJson('/admin/trainings/'.$training->id.'/archive')->assertStatus(503);
        $this->assertSame(1, Training::count());
        $this->assertSame(0, DB::table('training_participants')->count());
        $this->assertFalse($training->fresh()->is_archived);
    }

    public function test_render_consultation_screens(): void
    {
        $training = $this->training();
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->post('/admin/trainings/'.$training->id.'/participants', ['member_id' => 1])->assertSessionHasNoErrors();
        foreach (['index' => '/admin/trainings', 'create' => '/admin/trainings/create', 'details' => '/admin/trainings/'.$training->id, 'edit' => '/admin/trainings/'.$training->id.'/edit'] as $name => $url) {
            $response = $this->get($url)->assertOk();
            if (getenv('ASSOCMAP_EXPORT_TRAINING_FIXTURES') === '1') {
                $directory = storage_path('app/training-qa');
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
                $css = '/build/'.$manifest['resources/css/app.css']['file'];
                file_put_contents($directory.'/'.$name.'.html', str_replace('</head>', '<link rel="stylesheet" href="'.$css.'"></head>', $response->getContent()));
            }
        }
    }

    public function test_philippine_day_is_shared_by_filters_attendance_controls_and_saves(): void
    {
        // It is already September 26 in Cebu, even though the server date is September 25.
        $this->travelTo(Carbon::parse('2026-09-25 16:30:00', 'UTC'));
        try {
            $training = $this->training(['title' => 'Local day workshop', 'date_conducted' => '2026-09-26']);
            $this->withSession($this->sessionFor(1, 'System Administrator'));
            $url = '/admin/trainings/'.$training->id;
            $this->post($url.'/participants', ['member_id' => 1])->assertSessionHasNoErrors();
            $participant = DB::table('training_participants')->value('id');
            $this->get('/admin/trainings?period=upcoming')->assertOk()->assertDontSee('Local day workshop');
            $this->get('/admin/trainings?period=past')->assertOk()->assertSee('Local day workshop');
            $response = $this->get($url)->assertOk();
            $this->assertMatchesRegularExpression('/<option value="4"[^>]*>Present<\/option>/', $response->getContent());
            $this->assertDoesNotMatchRegularExpression('/<option value="4"[^>]*disabled/', $response->getContent());
            $this->patch($url.'/participants/'.$participant, ['attendance_status_id' => 4])->assertSessionHasNoErrors();
            $this->put($url, $this->payload(['date_conducted' => '2026-09-26']))->assertSessionHasNoErrors();
            $this->assertSame(4, DB::table('training_participants')->value('attendance_status_id'));
        } finally {
            $this->travelBack();
        }
    }

    public function test_database_failures_before_controller_writes_are_safe_and_preserve_form_input(): void
    {
        config(['app.debug' => true]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $failure = 'trainings';
        DB::connection()->beforeExecuting(function (string $query) use (&$failure): void {
            if ($failure && str_contains($query, 'from "'.$failure.'"')) {
                // Fail before PostgreSQL executes a statement so the surrounding test transaction remains usable.
                throw new QueryException('pgsql', 'select PRIVATE-SQL', ['PRIVATE-BINDING'], new PDOException('PRIVATE-CONNECTION'));
            }
        });
        $this->get('/admin/trainings')->assertStatus(503)->assertSee('Training Management is temporarily unavailable')
            ->assertDontSee('PRIVATE-SQL')->assertDontSee('PRIVATE-BINDING')->assertDontSee('PRIVATE-CONNECTION');
        $this->getJson('/admin/trainings/1')->assertStatus(503)->assertDontSee('PRIVATE-SQL');
        $failure = 'associations';
        $this->from('/admin/trainings/create')->post('/admin/trainings', $this->payload(['title' => 'Keep this title', 'unexpected' => 'Do not flash']))
            ->assertRedirect('/admin/trainings/create')->assertSessionHas('error')
            ->assertSessionHas('_old_input.title', 'Keep this title')->assertSessionMissing('_old_input.unexpected');
        $failure = null;
        $this->get('/admin/trainings/create')->assertOk()->assertSee('Keep this title');
        $failure = 'associations';
        $this->postJson('/admin/trainings', $this->payload())->assertStatus(503)->assertDontSee('PRIVATE-CONNECTION');
        $failure = null;
        $this->assertSame(0, Training::count());
    }
}
