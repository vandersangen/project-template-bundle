<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Mail;

use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use PHPUnit\Framework\TestCase;

class MailStatusTest extends TestCase
{
    public function testPendingStatus(): void
    {
        $this->assertEquals('pending', MailStatus::PENDING->value);
    }

    public function testSentStatus(): void
    {
        $this->assertEquals('sent', MailStatus::SENT->value);
    }

    public function testFailedStatus(): void
    {
        $this->assertEquals('failed', MailStatus::FAILED->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertEquals(MailStatus::PENDING, MailStatus::from('pending'));
        $this->assertEquals(MailStatus::SENT, MailStatus::from('sent'));
        $this->assertEquals(MailStatus::FAILED, MailStatus::from('failed'));
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        MailStatus::from('invalid');
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(MailStatus::tryFrom('invalid'));
    }

    public function testAllCasesExist(): void
    {
        $cases = MailStatus::cases();
        $this->assertCount(3, $cases);
    }
}
