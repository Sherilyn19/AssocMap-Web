<?php

declare(strict_types=1);

namespace App\Support;

final class MonitoringProgress
{
    public static function label(mixed $target, mixed $actual): string
    {
        if (!is_numeric($target) || !is_numeric($actual) || (float) $target < 0 || (float) $actual < 0) {
            return 'N/A (missing or invalid values)';
        }
        if ((float) $target === 0.0) {
            return 'N/A (zero target)';
        }

        return number_format((float) $actual / (float) $target * 100, 1).'%';
    }
}
