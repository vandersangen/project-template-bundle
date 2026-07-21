<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceTemplate;
use VanDerSangen\ProjectTemplateBundle\Invoice\Service\InvoiceTemplateService;
use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\EmailTemplateKey;
use VanDerSangen\ProjectTemplateBundle\Mail\Service\BrandedEmailMailer;
use VanDerSangen\ProjectTemplateBundle\Mail\Service\EmailTemplateService;
use VanDerSangen\ProjectTemplateBundle\Mail\Service\MailService;

class BrandedEmailMailerTest extends TestCase
{
    private EmailTemplateService $templateService;
    private InvoiceTemplateService $invoiceTemplateService;
    private MailService $mailService;
    private BrandedEmailMailer $mailer;

    protected function setUp(): void
    {
        $this->templateService = $this->createMock(EmailTemplateService::class);
        $this->invoiceTemplateService = $this->createMock(InvoiceTemplateService::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->mailer = new BrandedEmailMailer(
            $this->templateService,
            $this->invoiceTemplateService,
            $this->mailService,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testReturnsNullWithoutRecipients(): void
    {
        $this->mailService->expects($this->never())->method('createAndSend');

        $this->assertNull(
            $this->mailer->send('tool:1', EmailTemplateKey::SubscriptionCancelled, []),
        );
    }

    public function testSuppressedWhenTemplateDisabled(): void
    {
        $this->templateService->method('isEnabled')->willReturn(false);
        $this->mailService->expects($this->never())->method('createAndSend');

        $this->assertNull(
            $this->mailer->send('tool:1', EmailTemplateKey::SubscriptionCancelled, ['a@b.nl']),
        );
    }

    public function testSendsWithBrandingSenderFromInvoiceTemplate(): void
    {
        $this->templateService->method('isEnabled')->willReturn(true);
        $this->templateService->method('render')->willReturn([
            'subject' => 'Onderwerp',
            'html' => '<p>Body</p>',
        ]);
        $this->invoiceTemplateService->method('find')->willReturn(
            (new InvoiceTemplate())->setEmail('billing@acme.nl'),
        );

        $this->mailService->expects($this->once())
            ->method('createAndSend')
            ->with('Onderwerp', '<p>Body</p>', ['a@b.nl'], 'billing@acme.nl')
            ->willReturn(new Mail());

        $mail = $this->mailer->send('tool:1', EmailTemplateKey::SubscriptionCancelled, ['a@b.nl']);

        $this->assertInstanceOf(Mail::class, $mail);
    }

    public function testSenderOverrideWins(): void
    {
        $this->templateService->method('isEnabled')->willReturn(true);
        $this->templateService->method('render')->willReturn(['subject' => 'S', 'html' => 'H']);
        $this->invoiceTemplateService->method('find')->willReturn(
            (new InvoiceTemplate())->setEmail('billing@acme.nl'),
        );

        $this->mailService->expects($this->once())
            ->method('createAndSend')
            ->with('S', 'H', ['a@b.nl'], 'custom@acme.nl')
            ->willReturn(new Mail());

        $this->mailer->send(
            'tool:1',
            EmailTemplateKey::SubscriptionCancelled,
            ['a@b.nl'],
            [],
            ['senderEmail' => 'custom@acme.nl'],
        );
    }

    public function testPassesAttachmentsThroughToMailService(): void
    {
        $this->templateService->method('isEnabled')->willReturn(true);
        $this->templateService->method('render')->willReturn(['subject' => 'S', 'html' => 'H']);
        $this->invoiceTemplateService->method('find')->willReturn(new InvoiceTemplate());

        $attachments = [
            [
                'content' => base64_encode('%PDF-1.4'),
                'filename' => 'factuur-2026-001.pdf',
                'mime' => 'application/pdf',
            ],
        ];

        $this->mailService->expects($this->once())
            ->method('createAndSend')
            ->with('S', 'H', ['a@b.nl'], null, null, null, $attachments)
            ->willReturn(new Mail());

        $this->mailer->send(
            'tool:1',
            EmailTemplateKey::Invoice,
            ['a@b.nl'],
            [],
            [],
            $attachments,
        );
    }

    public function testSendsWithoutAttachmentsByDefault(): void
    {
        $this->templateService->method('isEnabled')->willReturn(true);
        $this->templateService->method('render')->willReturn(['subject' => 'S', 'html' => 'H']);
        $this->invoiceTemplateService->method('find')->willReturn(new InvoiceTemplate());

        $this->mailService->expects($this->once())
            ->method('createAndSend')
            ->with('S', 'H', ['a@b.nl'], null, null, null, null)
            ->willReturn(new Mail());

        $this->mailer->send('tool:1', EmailTemplateKey::SubscriptionCancelled, ['a@b.nl']);
    }

    public function testPassesBrandingAndDataIntoRender(): void
    {
        $this->templateService->method('isEnabled')->willReturn(true);
        $this->invoiceTemplateService->method('find')->willReturn(
            (new InvoiceTemplate())->setCompanyName('Acme BV')->setAccentColor('#123456'),
        );

        $this->templateService->expects($this->once())
            ->method('render')
            ->with(
                'tool:1',
                EmailTemplateKey::SubscriptionPendingCancellation,
                $this->callback(function (array $ctx): bool {
                    return ($ctx['companyName'] ?? null) === 'Acme BV'
                        && ($ctx['accentColor'] ?? null) === '#123456'
                        && ($ctx['endsAt'] ?? null) === '31 juli 2026';
                }),
            )
            ->willReturn(['subject' => 'S', 'html' => 'H']);
        $this->mailService->method('createAndSend')->willReturn(new Mail());

        $this->mailer->send(
            'tool:1',
            EmailTemplateKey::SubscriptionPendingCancellation,
            ['a@b.nl'],
            ['endsAt' => '31 juli 2026'],
        );
    }
}
