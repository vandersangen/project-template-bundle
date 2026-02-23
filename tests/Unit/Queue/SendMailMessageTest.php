<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use PHPUnit\Framework\TestCase;

class SendMailMessageTest extends TestCase
{
    public function testGetMailId(): void
    {
        $message = new SendMailMessage(42);
        $this->assertEquals(42, $message->getMailId());
    }

    public function testImplementsAsyncMessageInterface(): void
    {
        $message = new SendMailMessage(1);
        $this->assertInstanceOf(AsyncMessageInterface::class, $message);
    }

    public function testDifferentMailIds(): void
    {
        $message1 = new SendMailMessage(1);
        $message2 = new SendMailMessage(999);
        $this->assertEquals(1, $message1->getMailId());
        $this->assertEquals(999, $message2->getMailId());
    }
}
