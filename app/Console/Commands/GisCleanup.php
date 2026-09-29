<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class GisCleanup extends Command
{
    protected $signature = 'gis:cleanup';

    protected $description = 'Remove expired GIS previews and abandoned processing directories';

    public function handle(): int
    {
        $root = config('gis.storage_path');
        $count = 0;
        if (is_dir($root.'/previews')) {
            foreach (File::files($root.'/previews') as $file) {
                if (! $file->isLink() && preg_match('/^[1-9][0-9]*\.json$/D', $file->getFilename()) && $file->getMTime() < time() - 900) {
                    File::delete($file->getPathname());
                    $count++;
                }
            }
        }
        if (is_dir($root.'/work')) {
            foreach (File::directories($root.'/work') as $directory) {
                // Delete only our generated workspace names, never arbitrary paths or links.
                if (! is_link($directory) && preg_match('/^[a-f0-9-]{36}$/D', basename($directory)) && filemtime($directory) < time() - 3600) {
                    File::deleteDirectory($directory);
                    $count++;
                }
            }
        }
        $this->info('Removed '.$count.' expired GIS temporary items.');

        return self::SUCCESS;
    }
}
