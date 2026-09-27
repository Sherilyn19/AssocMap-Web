<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisIndexService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssociationDatabaseTestCase;

final class GisIndexTest extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // These records exist only in the isolated test schema and are rolled back.
        DB::unprepared(<<<'SQL'
            ALTER TABLE gis_locations ADD COLUMN location_name varchar, ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Landing area', latitude=10.5, longitude=123.5;
            INSERT INTO gis_locations (association_id,location_name,latitude,longitude,is_published)
                VALUES (1,'Office',10.5,123.5,false), (1,'Needs review',NULL,123.5,false), (1,'Invalid position',91,181,false), (1,'Zero position',0,0,false);
        SQL);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
    }

    public function test_page_keeps_multiple_locations_publication_and_area_context(): void
    {
        $response = $this->get('/admin/gis')->assertOk()->assertSee('GIS Mapping')
            ->assertSee('Landing area')->assertSee('Office')->assertSee('Published')->assertSee('Unpublished');
        $records = $response->viewData('records');
        $this->assertCount(5, $records);
        $this->assertSame(3, $records->where('valid', true)->count());
        $this->assertSame('Municipality A', $records->first()['municipality']);
        $this->assertSame('Barangay A', $records->first()['barangay']);
        $this->assertSame('SAAD', $records->first()['component']);
        $this->assertSame(0.0, $records->firstWhere('name', 'Zero position')['latitude']);
        $this->assertSame(1, $response->viewData('unmapped')->count());
    }

    public function test_empty_register_and_one_location_render_without_fake_pins(): void
    {
        DB::table('gis_locations')->where('id', '>', 1)->delete();
        $this->assertCount(1, app(GisIndexService::class)->overview()['records']);
        DB::table('gis_locations')->delete();
        $this->get('/admin/gis')->assertOk()->assertSee('No GIS locations have been recorded yet.')
            ->assertViewHas('records', fn ($records) => $records->isEmpty())
            ->assertViewHas('unmapped', fn ($records) => $records->count() === 2);
    }

    public function test_archive_state_is_shown_without_changing_location_records(): void
    {
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $before = DB::table('gis_locations')->orderBy('id')->get();
        $this->get('/admin/gis')->assertOk()->assertSee('Archived association');
        $this->assertEquals($before, DB::table('gis_locations')->orderBy('id')->get());
    }

    public function test_names_cannot_inject_html_or_end_the_json_data_block(): void
    {
        DB::table('gis_locations')->where('id', 1)->update(['location_name' => '</script><script>alert(1)</script>']);
        $this->get('/admin/gis')->assertOk()
            ->assertSee('&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('</script><script>alert(1)</script>', false);
    }

    public function test_route_checks_current_role_and_blocks_guests_and_inactive_accounts(): void
    {
        foreach ([2 => 'dashboard.officer', 3 => 'dashboard.member'] as $id => $dashboard) {
            $this->withSession($this->sessionFor($id, 'System Administrator'));
            $this->get('/admin/gis')->assertRedirect(route($dashboard))->assertDontSee('Landing area');
        }
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->get('/admin/gis')->assertRedirect(route('login'));
        $this->flushSession();
        $this->get('/admin/gis')->assertRedirect(route('login'));
    }

    public function test_database_failure_returns_a_clear_retry_page(): void
    {
        // Only the isolated test table is renamed; rollback restores it after this test.
        DB::statement('ALTER TABLE gis_locations RENAME TO unavailable_gis_locations');
        $this->get('/admin/gis')->assertStatus(503)->assertSee('GIS Mapping is temporarily unavailable')
            ->assertSee('Reload GIS Mapping')->assertDontSee('SQLSTATE')->assertDontSee('select *');
    }
}
