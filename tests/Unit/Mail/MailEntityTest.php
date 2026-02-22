<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Mail;

use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MailEntityTest extends TestCase
{
    private Mail $mail;

    protected function setUp(): void
    {
        $this->mail = new Mail();
    }

    public function testNewMailHasCreatedAt(): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $this->mail->getCreatedAt());
    }

    public function testNewMailHasNullId(): void
    {
        $this->assertNull($this->mail->getId());
    }

    public function testSetAndGetSender(): void
    {
        $this->mail->setSender('sender@example.com');
        $this->assertEquals('sender@example.com', $this->mail->getSender());
    }

    public function testSetAndGetReceiver(): void
    {
        $receivers = ['receiver1@example.com', 'receiver2@example.com'];
        $this->mail->setReceiver($receivers);
        $this->assertEquals($receivers, $this->mail->getReceiver());
    }

    public function testSetAndGetCc(): void
    {
        $cc = ['cc1@example.com', 'cc2@example.com'];
        $this->mail->setCc($cc);
        $this->assertEquals($cc, $this->mail->getCc());
    }

    public function testSetAndGetCcNull(): void
    {
        $this->mail->setCc(null);
        $this->assertNull($this->mail->getCc());
    }

    public function testSetAndGetBcc(): void
    {
        $bcc = ['bcc1@example.com'];
        $this->mail->setBcc($bcc);
        $this->assertEquals($bcc, $this->mail->getBcc());
    }

    public function testSetAndGetBccNull(): void
    {
        $this->mail->setBcc(null);
        $this->assertNull($this->mail->getBcc());
    }

    public function testSetAndGetTitle(): void
    {
        $this->mail->setTitle('Test Subject');
        $this->assertEquals('Test Subject', $this->mail->getTitle());
    }

    public function testSetAndGetBody(): void
    {
        $htmlBody = '<h1>Hello</h1><p>This is a test email.</p>';
        $this->mail->setBody($htmlBody);
        $this->assertEquals($htmlBody, $this->mail->getBody());
    }

    public function testSetAndGetStatus(): void
    {
        $this->mail->setStatus(MailStatus::SENT);
        $this->assertEquals(MailStatus::SENT, $this->mail->getStatus());
    }

    public function testDefaultStatusIsPending(): void
    {
        $this->assertEquals(MailStatus::PENDING, $this->mail->getStatus());
    }

    public function testSetAndGetSentAt(): void
    {
        $sentAt = new DateTimeImmutable();
        $this->mail->setSentAt($sentAt);
        $this->assertEquals($sentAt, $this->mail->getSentAt());
    }

    public function testSentAtDefaultIsNull(): void
    {
        $this->assertNull($this->mail->getSentAt());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01');
        $this->mail->setCreatedAt($createdAt);
        $this->assertEquals($createdAt, $this->mail->getCreatedAt());
    }

    public function testSetterReturnsSelf(): void
    {
        $result = $this->mail->setSender('test@example.com');
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setReceiver(['test@example.com']);
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setCc(['cc@example.com']);
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setBcc(['bcc@example.com']);
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setTitle('Title');
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setBody('Body');
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setStatus(MailStatus::PENDING);
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setCreatedAt(new DateTimeImmutable());
        $this->assertSame($this->mail, $result);
        $result = $this->mail->setSentAt(new DateTimeImmutable());
        $this->assertSame($this->mail, $result);
    }

    public function testToArray(): void
    {
        $this->mail->setSender('sender@example.com');
        $this->mail->setReceiver(['receiver@example.com']);
        $this->mail->setCc(['cc@example.com']);
        $this->mail->setBcc(['bcc@example.com']);
        $this->mail->setTitle('Test Title');
        $this->mail->setBody('<p>Test Body</p>');
        $this->mail->setStatus(MailStatus::SENT);
        $array = $this->mail->toArray();
        $this->assertNull($array['id']);
        $this->assertEquals('sender@example.com', $array['sender']);
        $this->assertEquals(['receiver@example.com'], $array['receiver']);
        $this->assertEquals(['cc@example.com'], $array['cc']);
        $this->assertEquals(['bcc@example.com'], $array['bcc']);
        $this->assertEquals('Test Title', $array['title']);
        $this->assertEquals('<p>Test Body</p>', $array['body']);
        $this->assertEquals('sent', $array['status']);
        $this->assertNotNull($array['createdAt']);
    }

    public function testToArrayWithEmptyOptionalFields(): void
    {
        $this->mail->setSender('sender@example.com');
        $this->mail->setReceiver(['receiver@example.com']);
        $this->mail->setTitle('Test');
        $this->mail->setBody('Body');
        $array = $this->mail->toArray();
        $this->assertNull($array['cc']);
        $this->assertNull($array['bcc']);
        $this->assertNull($array['sentAt']);
    }
}
