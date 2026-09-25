<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Training;
use App\Support\TrainingCalendar;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class TrainingCalendarTest extends TestCase
{
    public function test_attendance_opens_at_philippine_midnight_without_changing_utc_storage(): void
    {
        $training = new Training(['date_conducted' => '2026-09-26']);
        try {
            Carbon::setTestNow(Carbon::parse('2026-09-25 15:59:59', 'UTC'));
            $this->assertSame('2026-09-25', TrainingCalendar::today());
            $this->assertFalse($training->canRecordAttendance());

            Carbon::setTestNow(Carbon::parse('2026-09-25 16:00:00', 'UTC'));
            $this->assertSame('2026-09-26', TrainingCalendar::today());
            $this->assertTrue($training->canRecordAttendance());
            $this->assertSame('UTC', config('app.timezone'));
            $this->assertFalse((new Training)->canRecordAttendance());
        } finally {
            Carbon::setTestNow();
        }
    }
}
