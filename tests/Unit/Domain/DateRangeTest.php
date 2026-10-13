<?php

namespace Tests\Unit\Domain;

use App\Domain\DateRange;
use App\Domain\InvalidDateRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function test_accepts_end_after_start(): void
    {
        $range = new DateRange(new DateTimeImmutable('2026-10-01T00:00:00Z'), new DateTimeImmutable('2026-10-31T23:59:59Z'));
        $this->assertTrue($range->contains(new DateTimeImmutable('2026-10-14T14:15:00Z')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-11-01T00:00:00Z')));
    }

    public function test_accepts_end_equal_to_start(): void
    {
        $instant = new DateTimeImmutable('2026-10-13T00:00:00Z');
        $range = new DateRange($instant, $instant);
        $this->assertTrue($range->contains($instant));
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(InvalidDateRange::class);
        new DateRange(new DateTimeImmutable('2026-10-31T00:00:00Z'), new DateTimeImmutable('2026-10-01T00:00:00Z'));
    }
}
