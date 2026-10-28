<?php

namespace Reviewscouk\Reviews\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Reviewscouk\Reviews\Helper\OrderDate;

class OrderDateTest extends TestCase
{
    /** @var OrderDate */
    private $orderDate;

    protected function setUp(): void
    {
        $this->orderDate = new OrderDate();
    }

    public function testReturnsUtcDateFromMagentoCreatedAt(): void
    {
        $this->assertSame('2026-10-05', $this->orderDate->fromCreatedAt('2026-10-05 10:15:00'));
    }

    public function testReturnsNullForMissingValues(): void
    {
        $this->assertNull($this->orderDate->fromCreatedAt(null));
        $this->assertNull($this->orderDate->fromCreatedAt(''));
        $this->assertNull($this->orderDate->fromCreatedAt('   '));
    }

    public function testReturnsNullForUnparseableValues(): void
    {
        $this->assertNull($this->orderDate->fromCreatedAt('not a date'));
    }

    public function testReturnsNullForAFutureDate(): void
    {
        $tomorrow = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+1 day')
            ->format('Y-m-d H:i:s');

        $this->assertNull($this->orderDate->fromCreatedAt($tomorrow));
    }

    public function testAllowsToday(): void
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d') . ' 12:00:00';

        $this->assertSame(
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'),
            $this->orderDate->fromCreatedAt($today)
        );
    }
}
