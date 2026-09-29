<?php

declare(strict_types=1);

namespace App\Support;

final class GisCoordinate
{
    public static function canonical(string $value): string
    {
        // Normalize decimal text for duplicate checks without rounding through a float.
        if (strlen($value) > 1100 || ! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', trim($value), $parts)
            || (($parts[2] ?? '').($parts[3] ?? '')) === '' || abs((float) ($parts[4] ?? 0)) > 1000) {
            throw new \InvalidArgumentException('Invalid coordinate representation.');
        }
        $whole = $parts[2];
        $fraction = $parts[3] ?? '';
        $digits = $whole.$fraction;
        $position = strlen($whole) + (int) ($parts[4] ?? 0);
        if ($position <= 0) {
            $decimal = '0.'.str_repeat('0', -$position).$digits;
        } elseif ($position >= strlen($digits)) {
            $decimal = $digits.str_repeat('0', $position - strlen($digits));
        } else {
            $decimal = substr($digits, 0, $position).'.'.substr($digits, $position);
        }
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $decimal = $whole.($fraction === '' ? '' : '.'.$fraction);

        return ($parts[1] === '-' && $decimal !== '0' ? '-' : '').$decimal;
    }
}
