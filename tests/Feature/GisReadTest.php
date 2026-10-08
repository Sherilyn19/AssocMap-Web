<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisIndexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GisDatabaseTestCase;

final class GisReadTest extends GisDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(<<<'SQL'
            ALTER TABLE gis_locations ADD COLUMN location_name varchar(255), ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Published site', latitude=10, longitude=123, updated_at=now();
            UPDATE associations SET field_officer_id=1 WHERE id=2;
            INSERT INTO gis_locations (association_id,location_name,latitude,longitude,is_published,updated_at) VALUES
              (1,'Internal site',11,124,false,now()), (2,'Other site',12,125,false,now());
        SQL);
    }

    public function test_public_reader_is_an_allowlist_and_never_uses_signed_in_scope(): void
    {
        foreach ([null, 1, 2, 3] as $actor) {
            if ($actor !== null) {
                $this->withSession($this->sessionFor($actor, 'System Administrator'));
            }
            $response = $this->getJson('/map/locations')->assertOk()->assertJsonCount(1, 'records')
                ->assertJsonPath('records.0.name', 'Published site')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame(['name', 'association', 'municipality', 'barangay', 'component', 'latitude', 'longitude', 'project_title', 'commodity'], array_keys($response->json('records.0')));
        }
        $this->get('/map')->assertOk()->assertSee('Published site')->assertDontSee('Internal site');
        $this->get('/')->assertOk()->assertSee(route('gis.public'));
    }

    public function test_create_publish_unpublish_changes_the_public_http_results(): void
    {
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $id = $this->postJson('/admin/gis', ['association_id' => 1, 'location_name' => 'New point', 'latitude' => 0,
            'longitude' => 0, 'submission_token' => (string) Str::uuid()])->assertCreated()->json('id');
        $this->getJson('/map/locations')->assertJsonCount(1, 'records');
        $revision = app(GisIndexService::class)->overview()['records']->firstWhere('id', $id)['revision'];
        $this->patchJson('/admin/gis/'.$id.'/publish', [
            'revision' => $revision,
            'confirmed' => true,
        ])->assertOk();
        $this->getJson('/map/locations')->assertJsonCount(2, 'records');
        $revision = app(GisIndexService::class)->overview()['records']->firstWhere('id', $id)['revision'];
        $this->patchJson('/admin/gis/'.$id.'/unpublish', [
            'revision' => $revision,
            'confirmed' => true,
        ])->assertOk();
        $this->getJson('/map/locations')->assertJsonCount(1, 'records');
    }

    public function test_archived_invalid_and_unnamed_locations_are_excluded(): void
    {
        foreach ([['latitude' => 91], ['location_name' => '  '], ['longitude' => null]] as $invalid) {
            DB::table('gis_locations')->where('id', 1)->update($invalid);
            $this->getJson('/map/locations')->assertJsonCount(0, 'records');
            DB::table('gis_locations')->where('id', 1)->update(['latitude' => 10, 'longitude' => 123, 'location_name' => 'Published site']);
        }
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->getJson('/map/locations')->assertJsonCount(0, 'records');
    }

    public function test_scoped_readers_recheck_assignments_and_cannot_be_widened_by_parameters(): void
    {
        $this->getJson('/gis/locations')->assertRedirect(route('login'));
        foreach ([2 => 'Field Officer', 3 => 'Association Member'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role));
            $this->getJson('/gis/locations?association_id=2&user_id=1')->assertOk()->assertJsonCount(2, 'records')
                ->assertDontSee('Other site')->assertJsonPath('records.0.published', false);
            $this->get('/gis')->assertOk()->assertDontSee('Other site');
            $this->putJson('/admin/gis/1', [])->assertRedirect();
        }
        DB::table('users')->where('id', 3)->update(['association_id' => null]);
        $this->getJson('/gis/locations')->assertJsonCount(0, 'records');
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        $this->getJson('/gis/locations')->assertJsonCount(0, 'records');
        DB::table('users')->where('id', 2)->update(['is_active' => false]);
        $this->getJson('/gis/locations')->assertRedirect(route('login'));
    }

    public function test_filters_are_validated_literal_and_applied_server_side(): void
    {
        $this->getJson('/map/locations?municipality=Municipality%20A&component=SAAD')->assertJsonCount(1, 'records');
        $this->getJson('/map/locations?municipality=Municipality%20B')->assertJsonCount(0, 'records');
        $this->getJson('/map/locations?search=%25')->assertJsonCount(0, 'records');
        $this->getJson('/map/locations?search=published')->assertJsonCount(1, 'records');
        $this->getJson('/map/locations?page=-1')->assertUnprocessable();
        $this->getJson('/map/locations?search[]=x')->assertUnprocessable();
        $this->getJson('/map/locations?component='.str_repeat('x', 256))->assertUnprocessable();
        $this->postJson('/map/locations', [])->assertStatus(405);
        $this->deleteJson('/map/locations')->assertStatus(405);
    }

    public function test_pagination_bounds_response_and_html_escapes_names(): void
    {
        DB::statement("INSERT INTO gis_locations (association_id,location_name,latitude,longitude,is_published) SELECT 1, 'Site ' || n, 10, 123, true FROM generate_series(1,210) n");
        $this->getJson('/map/locations')->assertJsonCount(200, 'records')->assertJsonPath('has_more', true);
        $this->getJson('/map/locations?page=2')->assertJsonCount(11, 'records')->assertJsonPath('has_more', false);
        DB::table('gis_locations')->where('id', 1)->update(['location_name' => '<script>alert(1)</script>']);
        $this->get('/map')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
