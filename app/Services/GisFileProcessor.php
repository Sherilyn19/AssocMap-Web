<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class GisFileProcessor
{
    public function process(string $operation, string $format, string $input): string
    {
        abort_unless(in_array($operation, ['import', 'export'], true) && in_array($format, ['csv', 'geojson', 'kml', 'zip'], true), 422);
        if (strlen($input) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Input exceeds 5 MiB.']);
        }
        $directory = config('gis.storage_path').'/work/'.Str::uuid();
        File::ensureDirectoryExists($directory, 0700);
        try {
            $source = $directory.'/input';
            $target = $directory.'/output';
            File::put($source, $input);
            // Remove inherited credentials. Keep only the OS variables required to start Python.
            $environment = array_fill_keys(array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER))), false);
            foreach (['SystemRoot', 'WINDIR', 'PATH'] as $key) {
                if (getenv($key) !== false) {
                    $environment[$key] = getenv($key);
                }
            }
            $environment = array_replace($environment, ['PYTHONUTF8' => '1', 'PROJ_NETWORK' => 'OFF', 'GDAL_CACHEMAX' => '32', 'OMP_NUM_THREADS' => '1']);
            $environment['TEMP'] = $environment['TMP'] = $environment['TMPDIR'] = $directory;
            $process = new Process([(string) config('gis.python'), '-I', base_path('python/gis/worker.py'), $operation, $format, $source, $target], $directory, $environment);
            $process->setTimeout((float) config('gis.timeout'));
            $outputBytes = 0;
            $process->run(function (string $type, string $buffer) use (&$outputBytes, $process): void {
                $outputBytes += strlen($buffer);
                if ($outputBytes > 65536) {
                    $process->stop(0);
                    throw new \RuntimeException('Processor output exceeded its limit.');
                }
            });
            if (! $process->isSuccessful() || ! is_file($target)) {
                // Never return Python diagnostics, absolute paths, or native driver errors.
                throw ValidationException::withMessages(['file' => $process->getExitCode() === 3
                    ? 'GIS processing dependencies are unavailable. Contact the administrator.'
                    : 'The file could not be processed. Check its format, required fields, geometry, CRS and size limits.']);
            }
            if (filesize($target) > 10 * 1024 * 1024) {
                throw new \RuntimeException('Processor result exceeded its limit.');
            }

            return File::get($target);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            logger()->warning('GIS processor unavailable.', ['type' => $error::class]);
            throw ValidationException::withMessages(['file' => 'GIS processing did not finish safely. Check the configured Python runtime and try again.']);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
