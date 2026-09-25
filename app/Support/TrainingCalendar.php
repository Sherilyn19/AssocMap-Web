<?php

declare(strict_types=1);

namespace App\Support;

final class TrainingCalendar
{
    public static function today(): string
    {
        // Training dates are Philippine calendar dates, while stored timestamps remain in UTC.
        return now('Asia/Manila')->toDateString();
    }
}
