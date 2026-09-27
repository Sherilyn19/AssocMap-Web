<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AreaUnit;
use Illuminate\Validation\ValidationException;

final class MunicipalityNames
{
    public static function allowed(?int $id = null): array
    {
        $names = config('cebu-municipalities');
        // Retain legacy names on their own record without opening free-text creation.
        if ($id && ($existing = AreaUnit::query()->whereKey($id)->value('name'))) {
            $names[] = $existing;
        }

        return array_values(array_unique($names));
    }

    public static function validate(string $name, ?int $id = null): void
    {
        if (!in_array($name, self::allowed($id), true)) {
            throw ValidationException::withMessages(['name' => 'Select a municipality from the controlled list.']);
        }
    }
}
