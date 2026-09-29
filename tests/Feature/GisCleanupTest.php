<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GisCleanupTest extends TestCase
{
    public function test_cleanup_removes_only_expired_recognized_work_items(): void
    {
        $root = storage_path('app/gis-cleanup-test-'.Str::uuid());
        config(['gis.storage_path' => $root]);
        try {
            File::ensureDirectoryExists($root.'/previews');
            foreach (['1.json' => 1000, '2.json' => 0, 'notes.txt' => 1000] as $name => $age) {
                File::put($root.'/previews/'.$name, '{}');
                touch($root.'/previews/'.$name, time() - $age);
            }
            $expired = $root.'/work/'.Str::uuid();
            $fresh = $root.'/work/'.Str::uuid();
            foreach ([$expired => 4000, $fresh => 0, $root.'/work/unrelated' => 4000] as $path => $age) {
                File::ensureDirectoryExists($path);
                File::put($path.'/input', 'test');
                touch($path, time() - $age);
            }
            $this->artisan('gis:cleanup')->expectsOutput('Removed 2 expired GIS temporary items.')->assertSuccessful();
            $this->assertFileDoesNotExist($root.'/previews/1.json');
            $this->assertDirectoryDoesNotExist($expired);
            $this->assertFileExists($root.'/previews/2.json');
            $this->assertFileExists($root.'/previews/notes.txt');
            $this->assertDirectoryExists($fresh);
            $this->assertDirectoryExists($root.'/work/unrelated');
        } finally {
            File::deleteDirectory($root);
        }
    }
}
