<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GisFileProcessor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class GisProcessorTest extends TestCase
{
    public function test_worker_timeout_and_missing_runtime_leave_no_temporary_files(): void
    {
        $directory = storage_path('app/gis-processor-test-'.Str::uuid());
        config(['gis.storage_path' => $directory]);
        try {
            foreach ([['gis.timeout' => 0.001], ['gis.python' => $directory.'/missing-python']] as $override) {
                config($override);
                try {
                    app(GisFileProcessor::class)->process('import', 'csv', "association_id,location_name,latitude,longitude,crs\n1,Site,10,123,EPSG:4326");
                    $this->fail('The unavailable processor must not succeed.');
                } catch (ValidationException $error) {
                    $this->assertArrayHasKey('file', $error->errors());
                }
                $this->assertSame([], File::directories($directory.'/work'));
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_real_worker_applies_platform_limits_and_returns_valid_output(): void
    {
        $directory = storage_path('app/gis-processor-test-'.Str::uuid());
        config(['gis.storage_path' => $directory]);
        try {
            $output = app(GisFileProcessor::class)->process('import', 'csv', "association_id,location_name,latitude,longitude,crs\n1,Site,10,123,EPSG:4326");
            $this->assertSame('Point', json_decode($output, true)['geometry']);
            $this->assertSame([], File::directories($directory.'/work'));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
