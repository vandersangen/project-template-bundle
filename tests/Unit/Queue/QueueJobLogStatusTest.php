<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use PHPUnit\Framework\TestCase;

class QueueJobLogStatusTest extends TestCase
{
    public function testStartedValue(): void
    {
        $this->assertEquals('started', QueueJobLogStatus::STARTED->value);
    }

    public function testCompletedValue(): void
    {
        $this->assertEquals('completed', QueueJobLogStatus::COMPLETED->value);
    }

    public function testFailedValue(): void
    {
        $this->assertEquals('failed', QueueJobLogStatus::FAILED->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertEquals(QueueJobLogStatus::STARTED, QueueJobLogStatus::from('started'));
        $this->assertEquals(QueueJobLogStatus::COMPLETED, QueueJobLogStatus::from('completed'));
        $this->assertEquals(QueueJobLogStatus::FAILED, QueueJobLogStatus::from('failed'));
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        QueueJobLogStatus::from('invalid');
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $this->assertNull(QueueJobLogStatus::tryFrom('invalid'));
    }

    public function testCasesReturnsAllValues(): void
    {
        $cases = QueueJobLogStatus::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(QueueJobLogStatus::STARTED, $cases);
        $this->assertContains(QueueJobLogStatus::COMPLETED, $cases);
        $this->assertContains(QueueJobLogStatus::FAILED, $cases);
    }
}
