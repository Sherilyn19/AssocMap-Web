<?php

namespace Tests\Unit;

use App\Support\GisCoordinate;
use PHPUnit\Framework\TestCase;

final class GisCoordinateTest extends TestCase
{
    public function test_precision_and_equivalent_numeric_representations(): void
    {
        foreach (['001.2300' => '1.23', '-0.000' => '0', '+1e1' => '10', '.5' => '0.5', '1.23e-2' => '0.0123', '10.123456789123456789' => '10.123456789123456789'] as $value => $expected) {
            $this->assertSame($expected, GisCoordinate::canonical($value));
        }
        $this->expectException(\InvalidArgumentException::class);
        GisCoordinate::canonical('1e-9999999');
    }
}
