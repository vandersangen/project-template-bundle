<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;
use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use PHPUnit\Framework\TestCase;

class QueueJobLogEntityTest extends TestCase
{
    public function testNewEntityHasDefaultValues(): void
    {
        $log = new QueueJobLog();
        $this->assertNull($log->getId());
        $this->assertNull($log->getMessageClass());
        $this->assertEquals([], $log->getMessageData());
        $this->assertNull($log->getStdout());
        $this->assertNull($log->getStderr());
        $this->assertEquals(QueueJobLogStatus::STARTED, $log->getStatus());
        $this->assertNotNull($log->getStartedAt());
        $this->assertNull($log->getCompletedAt());
    }

    public function testSetAndGetMessageClass(): void
    {
        $log = new QueueJobLog();
        $result = $log->setMessageClass('App\Message\TestMessage');
        $this->assertSame($log, $result);
        $this->assertEquals('App\Message\TestMessage', $log->getMessageClass());
    }

    public function testSetAndGetMessageData(): void
    {
        $log = new QueueJobLog();
        $data = ['mailId' => 42, 'priority' => 'high'];
        $result = $log->setMessageData($data);
        $this->assertSame($log, $result);
        $this->assertEquals($data, $log->getMessageData());
    }

    public function testSetAndGetStdout(): void
    {
        $log = new QueueJobLog();
        $result = $log->setStdout('Processing mail ID 42...');
        $this->assertSame($log, $result);
        $this->assertEquals('Processing mail ID 42...', $log->getStdout());
    }

    public function testSetStdoutToNull(): void
    {
        $log = new QueueJobLog();
        $log->setStdout('some output');
        $log->setStdout(null);
        $this->assertNull($log->getStdout());
    }

    public function testSetAndGetStderr(): void
    {
        $log = new QueueJobLog();
        $result = $log->setStderr('Error: Connection refused');
        $this->assertSame($log, $result);
        $this->assertEquals('Error: Connection refused', $log->getStderr());
    }

    public function testSetStderrToNull(): void
    {
        $log = new QueueJobLog();
        $log->setStderr('some error');
        $log->setStderr(null);
        $this->assertNull($log->getStderr());
    }

    public function testSetAndGetStatus(): void
    {
        $log = new QueueJobLog();
        $result = $log->setStatus(QueueJobLogStatus::COMPLETED);
        $this->assertSame($log, $result);
        $this->assertEquals(QueueJobLogStatus::COMPLETED, $log->getStatus());
    }

    public function testSetStatusToFailed(): void
    {
        $log = new QueueJobLog();
        $log->setStatus(QueueJobLogStatus::FAILED);
        $this->assertEquals(QueueJobLogStatus::FAILED, $log->getStatus());
    }

    public function testSetAndGetStartedAt(): void
    {
        $log = new QueueJobLog();
        $date = new \DateTimeImmutable('2026-01-15 10:30:00');
        $result = $log->setStartedAt($date);
        $this->assertSame($log, $result);
        $this->assertEquals($date, $log->getStartedAt());
    }

    public function testSetAndGetCompletedAt(): void
    {
        $log = new QueueJobLog();
        $date = new \DateTimeImmutable('2026-01-15 10:31:00');
        $result = $log->setCompletedAt($date);
        $this->assertSame($log, $result);
        $this->assertEquals($date, $log->getCompletedAt());
    }

    public function testSetCompletedAtToNull(): void
    {
        $log = new QueueJobLog();
        $log->setCompletedAt(new \DateTimeImmutable());
        $log->setCompletedAt(null);
        $this->assertNull($log->getCompletedAt());
    }

    public function testToArray(): void
    {
        $log = new QueueJobLog();
        $log->setMessageClass('App\Message\TestMessage');
        $log->setMessageData(['mailId' => 42]);
        $log->setStdout('output');
        $log->setStderr('error');
        $log->setStatus(QueueJobLogStatus::FAILED);
        $log->setCompletedAt(new \DateTimeImmutable('2026-01-15 10:31:00'));
        $array = $log->toArray();
        $this->assertNull($array['id']);
        $this->assertEquals('App\Message\TestMessage', $array['messageClass']);
        $this->assertEquals(['mailId' => 42], $array['messageData']);
        $this->assertEquals('output', $array['stdout']);
        $this->assertEquals('error', $array['stderr']);
        $this->assertEquals('failed', $array['status']);
        $this->assertNotNull($array['startedAt']);
        $this->assertNotNull($array['completedAt']);
    }

    public function testToArrayWithMinimalData(): void
    {
        $log = new QueueJobLog();
        $log->setMessageClass('App\Message\Simple');
        $array = $log->toArray();
        $this->assertNull($array['stdout']);
        $this->assertNull($array['stderr']);
        $this->assertEquals('started', $array['status']);
        $this->assertNull($array['completedAt']);
    }

    public function testConstructorSetsStartedAt(): void
    {
        $before = new \DateTimeImmutable();
        $log = new QueueJobLog();
        $after = new \DateTimeImmutable();
        $this->assertGreaterThanOrEqual($before, $log->getStartedAt());
        $this->assertLessThanOrEqual($after, $log->getStartedAt());
    }
}
