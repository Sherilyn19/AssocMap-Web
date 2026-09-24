<?php

namespace Tests\Feature;

use App\Models\AreaUnit;
use App\Models\SubUnit;
use App\Services\AreaManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AssociationDatabaseTestCase;

/** Rollback-only PostgreSQL fixtures: no production records are changed. */
class AreaManagementTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared("ALTER TABLE area_units ADD COLUMN province varchar DEFAULT 'Cebu', ADD COLUMN address text, ADD COLUMN created_at timestamp DEFAULT now(), ADD COLUMN updated_at timestamp DEFAULT now();
            ALTER TABLE sub_units ADD COLUMN created_at timestamp DEFAULT now(), ADD COLUMN updated_at timestamp DEFAULT now();");
        (require base_path('database/migrations/2026_09_22_000001_harden_area_names.php'))->up();
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    public function test_lists_details_filters_and_summary_links_render(): void
    {
        $this->get('/admin/areas?tab=barangays')->assertOk()
            ->assertSee('Current Associations')->assertSee('Current Barangays')
            ->assertSee('data-area-management-page', false)->assertSee('role="tab"', false)
            ->assertSee('areas/barangays/2/archive', false)->assertDontSee('toggle-archive')
            ->assertDontSee('Total Coastline')->assertDontSee('Covered Barangays');
        $this->getJson('/admin/areas/municipalities/1')->assertOk()
            ->assertJsonPath('barangay_count', 1)->assertJsonPath('association_count', 2)
            ->assertJsonPath('barangays_truncated', false)->assertJsonPath('status', 'Current');
        $this->getJson('/admin/areas/barangays/1')->assertOk()->assertJsonPath('municipality', 'Municipality A');
        $this->getJson('/admin/areas/barangays/999')->assertNotFound()->assertDontSee('SQLSTATE');
        $this->get('/admin/areas?search[]=invalid')->assertRedirect(route('areas.index'))->assertSessionHasErrors('search');
        $this->getJson('/admin/areas?brgy_page=-1&per_page=999')->assertUnprocessable()->assertJsonValidationErrors(['brgy_page', 'per_page']);
    }

    public function test_create_update_normalization_and_scoped_duplicates(): void
    {
        $this->from('/admin/areas')->post('/admin/areas/municipalities', ['name' => '  New   Town ', 'address' => 'Address'])->assertSessionHas('success');
        $town = AreaUnit::where('name', 'New Town')->firstOrFail();
        $this->put('/admin/areas/municipalities/'.$town->id, ['name' => 'New Town', 'address' => 'Changed'])->assertSessionHas('success');
        $this->post('/admin/areas/municipalities', ['name' => 'new town'])->assertSessionHasErrorsIn('municipality', 'name');
        $this->post('/admin/areas/barangays', ['area_unit_id' => $town->id, 'name' => 'Barangay A'])->assertSessionHas('success');
        $this->post('/admin/areas/barangays', ['area_unit_id' => 1, 'name' => ' barangay   a '])->assertSessionHasErrorsIn('barangay', 'name');
        $this->assertSame('Changed', $town->fresh()->address);
        $audit = DB::table('audit_logs')->where('module', 'Area')->where('action_type', 'UPDATE')->value('details');
        $this->assertStringContainsString('Municipality changes', $audit);
        $this->assertStringContainsString('"before"', $audit);
        $this->assertStringContainsString('Changed', $audit);
    }

    public function test_failed_edit_preserves_only_its_draft_and_route_identity(): void
    {
        $this->from('/admin/areas?tab=barangays&brgy_search=Barangay')->put('/admin/areas/barangays/2', [
            'name' => 'My retained draft', 'area_unit_id' => 999, '_area_id' => 123, '_area_form' => 'municipality',
        ])->assertRedirect('/admin/areas?tab=barangays&brgy_search=Barangay')
            ->assertSessionHasErrorsIn('barangay', 'area_unit_id')
            ->assertSessionHas('_old_input._area_id', '2')
            ->assertSessionHas('_old_input._area_form', 'barangay');
        $this->get('/admin/areas?tab=barangays')->assertOk()->assertSee('My retained draft')->assertSee('data-recovery', false);
        $this->assertSame('Barangay B', SubUnit::findOrFail(2)->name);
    }

    public function test_archived_parent_is_rejected_by_requests_and_transaction_rechecks(): void
    {
        DB::table('area_units')->where('id', 2)->update(['is_archived' => true]);
        $this->post('/admin/areas/barangays', ['name' => 'Invalid child', 'area_unit_id' => 2])->assertSessionHasErrorsIn('barangay', 'area_unit_id');
        $this->put('/admin/areas/barangays/1', ['name' => 'Barangay A', 'area_unit_id' => 2])->assertSessionHasErrorsIn('barangay', 'area_unit_id');
        $this->expectException(ValidationException::class);
        app(AreaManagementService::class)->createBarangay(['name' => 'Direct service child', 'area_unit_id' => 2], 1);
    }

    public function test_archive_dependencies_restore_order_and_idempotence(): void
    {
        $this->patch('/admin/areas/municipalities/2/archive')->assertSessionHas('error');
        $this->patch('/admin/areas/barangays/1/archive')->assertSessionHas('error');
        $this->patch('/admin/areas/barangays/2/archive')->assertSessionHas('success');
        $this->patch('/admin/areas/barangays/2/archive')->assertSessionHas('success');
        $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'ARCHIVE')->count());
        $this->put('/admin/areas/barangays/2', ['name' => 'Cannot edit', 'area_unit_id' => 2])->assertSessionHasErrorsIn('barangay', 'name');
        $this->patch('/admin/areas/municipalities/2/archive')->assertSessionHas('success');
        $this->patch('/admin/areas/municipalities/2/archive')->assertSessionHas('success');
        $this->patch('/admin/areas/barangays/2/restore')->assertSessionHas('error');
        $this->assertTrue(SubUnit::findOrFail(2)->is_archived);
        $this->put('/admin/areas/municipalities/2', ['name' => 'Cannot edit'])->assertSessionHasErrorsIn('municipality', 'name');
        $this->patch('/admin/areas/municipalities/2/restore')->assertSessionHas('success');
        $this->patch('/admin/areas/barangays/2/restore')->assertSessionHas('success');
        $this->patch('/admin/areas/barangays/2/restore')->assertSessionHas('success');
        $this->assertFalse(SubUnit::findOrFail(2)->is_archived);
        $this->assertSame(2, DB::table('audit_logs')->where('action_type', 'RESTORE')->count());
    }

    public function test_historical_associations_block_moves_but_unreferenced_barangays_can_move(): void
    {
        DB::table('associations')->update(['is_archived' => true]);
        $this->put('/admin/areas/barangays/1', ['name' => 'Barangay A', 'area_unit_id' => 2])
            ->assertSessionHasErrorsIn('barangay', 'area_unit_id');
        $this->assertSame(1, (int) SubUnit::findOrFail(1)->area_unit_id);
        $this->put('/admin/areas/barangays/2', ['name' => 'Barangay B', 'area_unit_id' => 1])->assertSessionHas('success');
        $this->assertSame(1, (int) SubUnit::findOrFail(2)->area_unit_id);
    }

    public function test_audit_failure_rolls_back_and_preserves_draft_without_sql_leak(): void
    {
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_area CHECK (module <> 'Area')");
        $this->from('/admin/areas')->post('/admin/areas/municipalities', ['name' => 'Rollback Town', 'address' => 'Retain address'])
            ->assertSessionHas('error')->assertSessionHas('_old_input.address', 'Retain address');
        $this->assertFalse(AreaUnit::where('name', 'Rollback Town')->exists());
        $this->get('/admin/areas')->assertOk()->assertSee('Retain address')->assertDontSee('reject_area')->assertDontSee('SQLSTATE');
        $this->patch('/admin/areas/barangays/2/archive')->assertSessionHas('error');
        $this->assertFalse(SubUnit::findOrFail(2)->is_archived);
    }

    public function test_missing_actor_cannot_silently_skip_audit(): void
    {
        try {
            app(AreaManagementService::class)->createMunicipality(['name' => 'Unaudited'], null);
            $this->fail('Missing actor was accepted');
        } catch (\LogicException $exception) {
            $this->assertFalse(AreaUnit::where('name', 'Unaudited')->exists());
        }
    }

    public function test_recent_sort_is_descending_stable_and_detail_cap_is_explicit(): void
    {
        DB::table('area_units')->where('id', 1)->update(['updated_at' => '2020-01-01']);
        DB::table('area_units')->where('id', 2)->update(['updated_at' => '2026-01-01']);
        $service = app(AreaManagementService::class);
        $this->assertSame(2, $service->listMunicipalities(['muni_sort' => 'updated_at'])->first()->id);
        DB::table('sub_units')->where('id', 1)->update(['updated_at' => '2020-01-01']);
        DB::table('sub_units')->where('id', 2)->update(['updated_at' => '2026-01-01']);
        $this->assertSame(2, $service->listBarangays(['brgy_sort' => 'updated_at'])->first()->id);
        for ($i = 0; $i < 52; $i++) {
            DB::table('sub_units')->insert(['name' => 'Extra '.$i, 'area_unit_id' => 1]);
        }
        $this->getJson('/admin/areas/municipalities/1')->assertOk()->assertJsonPath('total_barangay_count', 53)
            ->assertJsonPath('barangays_truncated', true)->assertJsonCount(50, 'barangays');
        $this->assertStringContainsString('tab=barangays', $service->listBarangays(['area_unit_id' => 1])->url(2));
        DB::table('area_units')->where('id', 2)->update(['is_archived' => true]);
        $this->get('/admin/areas?tab=barangays&area_unit_id=2')->assertOk()->assertSee('Municipality B (Archived)');
    }

    public function test_area_access_rechecks_roles_account_state_and_missing_sessions(): void
    {
        $this->withSession($this->sessionFor(2, 'System Administrator'))->get('/admin/areas')->assertRedirect(route('dashboard.officer'));
        $this->withSession($this->sessionFor(3, 'System Administrator'))->post('/admin/areas/municipalities', ['name' => 'Forbidden'])->assertRedirect(route('dashboard.member'));
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'))->patch('/admin/areas/barangays/2/archive')->assertRedirect(route('login'));
        $this->flushSession();
        $this->get('/admin/areas')->assertRedirect(route('login'));
        $this->assertFalse(AreaUnit::where('name', 'Forbidden')->exists());
        $this->assertFalse(SubUnit::findOrFail(2)->is_archived);
    }

    public function test_normalized_database_indexes_reject_direct_duplicate_writes(): void
    {
        $this->expectException(QueryException::class);
        DB::table('area_units')->insert(['name' => ' municipality   a ']);
    }

    public function test_invalid_shapes_lengths_and_missing_records_fail_safely(): void
    {
        $this->postJson('/admin/areas/municipalities', ['name' => ['invalid'], 'address' => str_repeat('x', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'address']);
        $this->postJson('/admin/areas/barangays', ['name' => str_repeat('x', 256), 'area_unit_id' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'area_unit_id']);
        $this->put('/admin/areas/municipalities/999', ['name' => 'Missing town'])->assertSessionHas('error');
        $this->patch('/admin/areas/barangays/999/archive')->assertSessionHas('error');
    }

    public function test_read_and_pre_controller_database_errors_do_not_leak_details(): void
    {
        DB::statement('ALTER TABLE area_units RENAME TO unavailable_area_fixture');
        foreach ([
            fn () => $this->getJson('/admin/areas/municipalities/1')->assertStatus(503)->assertDontSee('SQLSTATE'),
            fn () => $this->get('/admin/areas')->assertStatus(503)->assertSee('temporarily unavailable')->assertDontSee('SQLSTATE'),
            fn () => $this->postJson('/admin/areas/municipalities', ['name' => 'Database unavailable'])
                ->assertStatus(503)->assertDontSee('SQLSTATE')->assertDontSee('unavailable_area_fixture'),
        ] as $request) {
            // Production reads use autocommit; the rollback-only test has an outer
            // transaction. A savepoint isolates each intentionally failing SQL request.
            DB::beginTransaction();
            try {
                $request();
            } finally {
                DB::rollBack();
            }
        }
    }

    public function test_browser_fixtures_export_only_synthetic_area_records(): void
    {
        $response = $this->get('/admin/areas')->assertOk();
        if (getenv('ASSOCMAP_EXPORT_AREA_FIXTURES') !== '1') {
            return;
        }
        $dir = storage_path('framework/testing/area');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $assets = '<script type="module" src="/build/'.$manifest['resources/js/app.js']['file'].'"></script>';
        $assets .= '<link rel="stylesheet" href="/build/'.$manifest['resources/css/app.css']['file'].'">';
        $html = str_replace('</head>', $assets.'</head>', $response->getContent());
        $html = str_replace([url('/'), str_replace('/', '\\/', url('/'))], ['http://127.0.0.1:8098', 'http:\\/\\/127.0.0.1:8098'], $html);
        $html = preg_replace('/(name="_token" value=")[^"]+/', '$1synthetic-preview-token', $html);
        file_put_contents($dir.'/index.html', $html);
        $this->from('/admin/areas?tab=barangays')->put('/admin/areas/barangays/2', ['name' => 'Retained browser draft', 'area_unit_id' => 999])
            ->assertSessionHasErrorsIn('barangay', 'area_unit_id');
        $recovery = $this->get('/admin/areas?tab=barangays')->assertOk()->getContent();
        $recovery = str_replace('</head>', $assets.'</head>', $recovery);
        $recovery = str_replace([url('/'), str_replace('/', '\\/', url('/'))], ['http://127.0.0.1:8098', 'http:\\/\\/127.0.0.1:8098'], $recovery);
        $recovery = preg_replace('/(name="_token" value=")[^"]+/', '$1synthetic-preview-token', $recovery);
        file_put_contents($dir.'/recovery.html', $recovery);
        $service = app(AreaManagementService::class);
        foreach (['municipalities' => $service->viewMunicipality(1), 'barangays' => $service->viewBarangay(1)] as $type => $record) {
            if (isset($record['barangays_url'])) {
                $record['barangays_url'] = 'http://127.0.0.1:8098/admin/areas?tab=barangays&area_unit_id=1';
            }
            file_put_contents($dir.'/'.$type.'.json', json_encode($record, JSON_THROW_ON_ERROR));
        }
    }
}
