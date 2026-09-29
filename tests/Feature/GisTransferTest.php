<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisFileProcessor;
use App\Services\GisTransferService;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\GisDatabaseTestCase;

final class GisTransferTest extends GisDatabaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('app/gis-test-'.Str::uuid());
        config(['gis.storage_path' => $this->directory]);
        DB::unprepared("ALTER TABLE gis_locations ADD COLUMN location_name varchar(255), ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Existing site',latitude=10,longitude=123,updated_at=now();");
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    private function csv(string $rows = "1,Imported site,11,124,EPSG:4326\n"): string
    {
        return "association_id,location_name,latitude,longitude,crs\n".$rows;
    }

    private function preview(string $input, string $format = 'csv'): array
    {
        $file = UploadedFile::fake()->createWithContent('locations.'.$format, $input);
        $this->post('/admin/gis/import/preview', ['format' => $format, 'file' => $file])->assertRedirect(route('gis.transfer'));

        return app(GisTransferService::class)->savedPreview(1);
    }

    public function test_real_csv_preview_confirm_replay_and_audit(): void
    {
        $preview = $this->preview($this->csv());
        $this->assertSame(1, $preview['accepted']);
        $this->assertSame(1, DB::table('gis_locations')->count());
        $this->get('/admin/gis/transfer')->assertOk()->assertSee('Imported site');
        for ($i = 0; $i < 2; $i++) {
            $this->post('/admin/gis/import/confirm', ['token' => $preview['token'], 'confirmed' => 1])->assertRedirect(route('gis.transfer'));
        }
        $this->assertDatabaseHas('gis_locations', ['location_name' => 'Imported site', 'is_published' => false]);
        $this->assertSame(2, DB::table('gis_locations')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('module', 'GIS')->count());
        $this->assertSame(1, DB::table('gis_submissions')->count());
    }

    public function test_real_geojson_and_kml_import_export_round_trips(): void
    {
        foreach (['geojson', 'kml', 'csv'] as $format) {
            $response = $this->postJson('/admin/gis/export', ['format' => $format, 'publication' => 'published'])->assertOk();
            $result = json_decode(app(GisFileProcessor::class)->process('import', $format, $response->getContent()), true);
            $this->assertSame('Existing site', $result['records'][0]['location_name']);
            $this->assertSame('EPSG:4326', $result['crs']);
            $preview = $this->preview($response->getContent(), $format);
            $this->assertSame(1, $preview['duplicates']);
            $this->assertSame(0, $preview['accepted']);
        }
        $this->postJson('/admin/gis/export', ['format' => 'csv', 'publication' => 'unpublished'])->assertUnprocessable();
        $this->postJson('/admin/gis/export', ['format' => 'exe'])->assertUnprocessable();
    }

    public function test_invalid_duplicate_archived_and_changed_rows_never_partially_save(): void
    {
        foreach (["1,Valid,11,124,EPSG:4326\n1,Invalid,91,124,EPSG:4326\n",
            "1,One,11,124,EPSG:4326\n1,Two,11,124,EPSG:4326\n",
            "1, existing   site ,11,124,EPSG:4326\n", "999,Missing,11,124,EPSG:4326\n"] as $rows) {
            $preview = $this->preview($this->csv($rows));
            $this->assertSame(0, $preview['accepted']);
            $this->postJson('/admin/gis/import/confirm', ['token' => $preview['token'], 'confirmed' => 1])->assertUnprocessable();
        }
        $preview = $this->preview($this->csv());
        DB::table('associations')->where('id', 1)->update(['is_archived' => true]);
        $this->postJson('/admin/gis/import/confirm', ['token' => $preview['token'], 'confirmed' => 1])->assertUnprocessable();
        $this->assertSame(1, DB::table('gis_locations')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_export_matches_search_across_display_fields_and_combined_filters(): void
    {
        DB::table('associations')->where('id', 1)->update(['name' => 'Coastal group']);
        $filters = ['format' => 'csv', 'search' => 'site Coastal', 'municipality' => 1, 'barangay' => 1, 'component' => 1, 'publication' => 'published'];
        $response = $this->postJson('/admin/gis/export', $filters)->assertOk();
        $this->assertStringContainsString('Existing site', $response->getContent());
        $this->postJson('/admin/gis/export', array_replace($filters, ['municipality' => 2]))->assertUnprocessable();
        $this->postJson('/admin/gis/export', array_replace($filters, ['search' => '%']))->assertUnprocessable();
    }

    public function test_audit_failure_rolls_back_entire_import_and_receipts(): void
    {
        $preview = $this->preview($this->csv("1,First,11,124,EPSG:4326\n1,Second,12,125,EPSG:4326\n"));
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT reject_import CHECK (module <> 'GIS')");
        $this->postJson('/admin/gis/import/confirm', ['token' => $preview['token'], 'confirmed' => 1])->assertStatus(503);
        $this->assertSame(1, DB::table('gis_locations')->count());
        $this->assertSame(0, DB::table('gis_submissions')->count());
    }

    public function test_unauthorized_and_invalid_requests_are_blocked(): void
    {
        foreach ([2 => 'Field Officer', 3 => 'Association Member'] as $id => $role) {
            $this->withSession($this->sessionFor($id, $role));
            $this->get('/admin/gis/transfer')->assertRedirect();
            foreach (['import/preview', 'import/confirm', 'export'] as $path) {
                $this->postJson('/admin/gis/'.$path, [])->assertRedirect();
            }
        }
        $this->withSession($this->sessionFor(1, 'System Administrator'));
        $this->postJson('/admin/gis/import/confirm', ['token' => (string) Str::uuid(), 'confirmed' => 1])->assertConflict();
        $this->postJson('/admin/gis/import/preview', ['format' => 'csv', 'file' => UploadedFile::fake()->create('large.csv', 5121)])->assertUnprocessable();
        $this->postJson('/admin/gis/import/preview', ['format' => 'zip', 'file' => UploadedFile::fake()->createWithContent('bad.zip', 'not a zip')])->assertUnprocessable();
        $this->assertSame([], File::directories($this->directory.'/work'));
    }

    public function test_laravel_rejects_untrusted_processor_contract_and_rows(): void
    {
        $this->mock(GisFileProcessor::class)->shouldReceive('process')->once()->andReturn('{bad json');
        $this->postJson('/admin/gis/import/preview', ['format' => 'csv', 'file' => UploadedFile::fake()->createWithContent('x.csv', 'x')])->assertUnprocessable();
        $this->mock(GisFileProcessor::class)->shouldReceive('process')->once()->andReturn(json_encode([
            'version' => 1, 'geometry' => 'Point', 'crs' => 'EPSG:4326', 'records' => [['association_id' => 1, 'location_name' => 'Bad', 'latitude' => 100, 'longitude' => 0, 'crs' => 'EPSG:4326']],
        ]));
        $preview = $this->preview('x');
        $this->assertSame(0, $preview['accepted']);
    }

    public function test_thousand_row_import_is_atomic_and_preserves_decimal_text(): void
    {
        $rows = '';
        for ($i = 0; $i < 1000; $i++) {
            $rows .= '1,Point '.$i.',11.'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)."123456789123456789,124,EPSG:4326\n";
        }
        $start = microtime(true);
        $preview = $this->preview($this->csv($rows));
        $this->assertSame(1000, $preview['accepted']);
        $this->post('/admin/gis/import/confirm', ['token' => $preview['token'], 'confirmed' => 1])->assertRedirect(route('gis.transfer'));
        $this->assertSame(1001, DB::table('gis_locations')->count());
        $this->assertSame(1000, DB::table('audit_logs')->count());
        $this->assertSame('11.0000123456789123456789', DB::table('gis_locations')->where('location_name', 'Point 0')->value('latitude'));
        fwrite(STDOUT, '\nGIS 1000-row preview and save: '.round(microtime(true) - $start, 2).' seconds.\n');
    }
}
