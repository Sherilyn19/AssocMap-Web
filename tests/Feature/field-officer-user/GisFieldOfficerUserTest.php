<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisIndexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GisDatabaseTestCase;

final class GisFieldOfficerUserTest extends GisDatabaseTestCase
{
    public function test_assigned_management_and_cross_association_denial(): void
    {
        DB::unprepared("ALTER TABLE gis_locations ADD COLUMN location_name varchar, ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Assigned site',latitude=10,longitude=123,updated_at=now();
            UPDATE associations SET field_officer_id=1 WHERE id=2;
            INSERT INTO gis_locations (association_id,location_name,latitude,longitude,is_published,updated_at) VALUES (2,'Other site',12,125,false,now());");
        $this->withSession($this->sessionFor(2, 'Field Officer'));
        $this->get('/officer/gis')->assertOk()->assertSee('Assigned site')->assertDontSee('Other site');
        $fields = ['association_id' => 1, 'location_name' => 'Officer point', 'latitude' => 11, 'longitude' => 124, 'submission_token' => (string) Str::uuid()];
        $id = $this->postJson('/officer/gis', $fields)->assertCreated()->json('id');
        $revision = app(GisIndexService::class)->overview(2)['records']->firstWhere('id', $id)['revision'];
        $this->patchJson('/officer/gis/'.$id.'/publish', [
            'revision' => $revision,
            'confirmed' => true,
        ])->assertOk();
        $revision = app(GisIndexService::class)->overview(2)['records']->firstWhere('id', $id)['revision'];
        $this->putJson('/officer/gis/'.$id, array_replace($fields, ['revision' => $revision, 'location_name' => 'Edited']))->assertOk();
        $this->postJson('/officer/gis', array_replace($fields, ['association_id' => 2]))->assertNotFound();
        // Valid confirmation allows this request to test association access restrictions.
        $this->patchJson('/officer/gis/2/publish', [
            'revision' => $revision,
            'confirmed' => true,
        ])->assertNotFound();
        $this->putJson('/officer/gis/2', $fields + compact('revision'))->assertNotFound();
        DB::table('associations')->where('id', 1)->update(['field_officer_id' => 1]);
        // Reassignment must block access even when confirmation is supplied.
        $this->patchJson('/officer/gis/'.$id.'/unpublish', [
            'revision' => $revision,
            'confirmed' => true,
        ])->assertNotFound();
        $this->withSession($this->sessionFor(3, 'Association Member'));
        $this->postJson('/officer/gis', $fields)->assertRedirect();
        // CREATE, PUBLISH, UPDATE, and automatic UNPUBLISH.
        $this->assertSame(4, DB::table('audit_logs')->where('user_id', 2)->where('module', 'GIS')->count());
    }
}
