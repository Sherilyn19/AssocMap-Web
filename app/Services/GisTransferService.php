<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\User;
use App\Support\GisCoordinate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GisTransferService
{
    private function authorize(int $actorId): void
    {
        abort_unless(User::whereKey($actorId)->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('role_name', 'System Administrator'))->exists(), 403);
    }

    public function preview(string $input, string $format, string $filename, int $actorId): array
    {
        $this->authorize($actorId);
        try {
            $result = json_decode(app(GisFileProcessor::class)->process('import', $format, $input), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw ValidationException::withMessages(['file' => 'The processor returned an unreadable result.']);
        }
        if (! is_array($result) || ($result['version'] ?? null) !== 1 || ($result['geometry'] ?? null) !== 'Point' || ($result['crs'] ?? null) !== 'EPSG:4326'
            || ! is_array($result['records'] ?? null) || ! array_is_list($result['records']) || count($result['records']) < 1 || count($result['records']) > 1000) {
            throw ValidationException::withMessages(['file' => 'The processor returned an unsupported dataset.']);
        }
        $rows = [];
        $errors = [];
        if (! empty($result['errors'])) {
            $errors[] = 'The file processor found invalid rows. Correct the file before importing.';
        }
        foreach ($result['records'] as $index => $row) {
            $validator = Validator::make(is_array($row) ? $row : [], [
                'association_id' => ['required', 'integer', 'min:1', 'max:999999999999999'],
                'location_name' => ['required', 'string', 'max:255'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'crs' => ['required', 'in:EPSG:4326'],
            ]);
            if ($validator->fails() || strlen((string) ($row['latitude'] ?? '')) > 128 || strlen((string) ($row['longitude'] ?? '')) > 128) {
                $errors[] = 'Row '.($index + 1).': invalid identifier, name, coordinates or CRS.';

                continue;
            }
            $values = $validator->validated();
            $values['location_name'] = trim($values['location_name']);
            if ($values['location_name'] === '' || ! is_finite((float) $values['latitude']) || ! is_finite((float) $values['longitude'])) {
                $errors[] = 'Row '.($index + 1).': empty name or invalid coordinate.';

                continue;
            }
            try {
                $values['latitude'] = GisCoordinate::canonical((string) $values['latitude']);
                $values['longitude'] = GisCoordinate::canonical((string) $values['longitude']);
            } catch (\InvalidArgumentException $error) {
                $errors[] = 'Row '.($index + 1).': unsupported coordinate precision or exponent.';

                continue;
            }
            $rows[] = $values + ['row' => $index + 1, 'submission_token' => (string) Str::uuid()];
        }
        [$businessErrors, $duplicates] = $this->checkRows($rows);
        $names = Association::whereIn('id', array_column($rows, 'association_id'))->pluck('name', 'id');
        foreach ($rows as &$row) {
            $row['association'] = $names->get($row['association_id'], 'Association unavailable');
        }
        unset($row);
        $errors = array_merge($errors, $businessErrors);
        $preview = ['token' => (string) Str::uuid(), 'actor_id' => $actorId, 'expires_at' => time() + 900,
            'filename' => mb_substr(basename(str_replace('\\', '/', $filename)), 0, 200), 'format' => $format,
            'detected_crs' => mb_substr((string) ($result['detected_crs'] ?? 'Unknown'), 0, 200), 'target_crs' => 'EPSG:4326',
            'total' => count($result['records']), 'accepted' => $errors ? 0 : count($rows), 'rejected' => $errors ? count($result['records']) : 0,
            'duplicates' => $duplicates, 'warnings' => ['All imported locations remain unpublished. Any error rejects the complete file.', 'Coordinates are converted to WGS84 decimal degrees.'],
            'errors' => $errors, 'records' => $rows];
        File::ensureDirectoryExists(config('gis.storage_path').'/previews', 0700);
        File::replace($this->path($actorId), json_encode($preview, JSON_THROW_ON_ERROR), 0600);

        return $preview;
    }

    public function savedPreview(int $actorId): ?array
    {
        $path = $this->path($actorId);
        if (! is_file($path)) {
            return null;
        }
        $preview = json_decode(File::get($path), true, 32, JSON_THROW_ON_ERROR);
        if (($preview['expires_at'] ?? 0) < time()) {
            File::delete($path);

            return null;
        }

        $preview['completed'] = count($preview['records']) > 0 && DB::table('gis_submissions')
            ->where('user_id', $actorId)->whereIn('token', array_column($preview['records'], 'submission_token'))
            ->whereNotNull('location_id')->count() === count($preview['records']);

        return $preview;
    }

    public function confirm(string $token, int $actorId): int
    {
        $this->authorize($actorId);
        $preview = $this->savedPreview($actorId);
        abort_unless($preview && hash_equals($preview['token'], $token), 409, 'This preview expired or was replaced. Upload the file again.');
        abort_if($preview['errors'] || ! $preview['accepted'], 422, 'Correct all errors before importing.');

        return app(AssociationDatabase::class)->run(function () use ($preview, $actorId): int {
            // Sorted parent locks serialize imports with edits, publication and association archival.
            $ids = array_unique(array_column($preview['records'], 'association_id'));
            sort($ids, SORT_NUMERIC);
            Association::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $tokens = array_column($preview['records'], 'submission_token');
            $receipts = DB::table('gis_submissions')->where('user_id', $actorId)->whereIn('token', $tokens)->whereNotNull('location_id')->count();
            if ($receipts === count($tokens)) {
                return $receipts;
            }
            abort_if($receipts !== 0, 409, 'The import receipt is inconsistent. Ask an administrator to review it.');
            [$errors] = $this->checkRows($preview['records']);
            if ($errors) {
                throw ValidationException::withMessages(['file' => 'Records changed since preview: '.$errors[0]]);
            }

            return app(GisManagementService::class)->importRows($preview['records'], $actorId);
        });
    }

    private function checkRows(array $rows): array
    {
        $associations = Association::whereIn('id', array_column($rows, 'association_id'))->get()->keyBy('id');
        $errors = [];
        $duplicates = 0;
        $seenNames = $seenCoordinates = [];
        $existing = DB::table('gis_locations')->whereIn('association_id', array_column($rows, 'association_id'))
            ->select(['association_id', 'location_name', 'latitude', 'longitude'])->limit(10001)->get();
        if ($existing->count() > 10000) {
            throw ValidationException::withMessages(['file' => 'The target associations exceed the 10,000-location comparison limit. Split the import by association.']);
        }
        foreach ($existing as $location) {
            $name = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $location->location_name)));
            $seenNames[$location->association_id.'|'.$name] = true;
            if ($location->latitude !== null && $location->longitude !== null) {
                try {
                    $seenCoordinates[$location->association_id.'|'.GisCoordinate::canonical((string) $location->latitude).'|'.GisCoordinate::canonical((string) $location->longitude)] = true;
                } catch (\InvalidArgumentException $error) {
                    throw ValidationException::withMessages(['file' => 'An existing coordinate needs review before importing into this association.']);
                }
            }
        }
        foreach ($rows as $row) {
            $association = $associations->get($row['association_id']);
            if (! $association || $association->is_archived) {
                $errors[] = 'Row '.$row['row'].': association is missing or archived.';

                continue;
            }
            $name = mb_strtolower(preg_replace('/\s+/u', ' ', trim($row['location_name'])));
            $nameKey = $row['association_id'].'|'.$name;
            $pointKey = $row['association_id'].'|'.$row['latitude'].'|'.$row['longitude'];
            if (isset($seenNames[$nameKey]) || isset($seenCoordinates[$pointKey])) {
                $duplicates++;
                $errors[] = 'Row '.$row['row'].': duplicate association/location name or coordinates.';
            }
            $seenNames[$nameKey] = $seenCoordinates[$pointKey] = true;
        }

        return [$errors, $duplicates];
    }

    private function path(int $actorId): string
    {
        return config('gis.storage_path').'/previews/'.$actorId.'.json';
    }
}
