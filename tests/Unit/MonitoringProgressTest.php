<?php

namespace Tests\Unit;

use App\Support\MonitoringProgress;
use PHPUnit\Framework\TestCase;

final class MonitoringProgressTest extends TestCase
{
    public function test_actual_is_divided_by_target_with_safe_missing_and_zero_values(): void
    {
        $this->assertSame('80.0%', MonitoringProgress::label(100, 80));
        $this->assertSame('125.0%', MonitoringProgress::label(80, 100));
        $this->assertSame('0.0%', MonitoringProgress::label(100, 0));
        $this->assertSame('N/A (zero target)', MonitoringProgress::label(0, 20));
        foreach ([[null, 2], [5, null], [-1, 5], [5, -1]] as [$target, $actual]) {
            $this->assertSame('N/A (missing or invalid values)', MonitoringProgress::label($target, $actual));
        }
    }
}
