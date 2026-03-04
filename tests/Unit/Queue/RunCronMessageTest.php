<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\RunCronMessage;

class RunCronMessageTest extends TestCase
{
    public function testConstructorAndGetCronId(): void
    {
        $message = new RunCronMessage(42);
        $this->assertSame(42, $message->getCronId());
    }

    public function testImplementsAsyncMessageInterface(): void
    {
        $message = new RunCronMessage(1);
        $this->assertInstanceOf(AsyncMessageInterface::class, $message);
    }
}
