<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Mail\Entity\EmailTemplate;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\EmailTemplateKey;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\EmailTemplateRepository;
use VanDerSangen\ProjectTemplateBundle\Mail\Service\EmailTemplateService;

class EmailTemplateServiceTest extends TestCase
{
    private EmailTemplateRepository $repository;
    private EmailTemplateService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepository::class);
        $this->service = new EmailTemplateService($this->repository);
    }

    public function testRenderFallsBackToDutchDefaultWhenNoOverride(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);

        $result = $this->service->render('tool:1', EmailTemplateKey::SubscriptionCancelled, []);

        $this->assertSame('Je abonnement is opgezegd', $result['subject']);
        $this->assertStringContainsString('Abonnement opgezegd', $result['html']);
        $this->assertStringContainsString('<!doctype html>', $result['html']);
    }

    public function testRenderFillsDataPlaceholders(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);

        $result = $this->service->render(
            'tool:1',
            EmailTemplateKey::SubscriptionPendingCancellation,
            ['endsAt' => '31 juli 2026'],
        );

        $this->assertStringContainsString('31 juli 2026', $result['html']);
        $this->assertStringNotContainsString('{{ endsAt }}', $result['html']);
    }

    public function testRenderStripsUnknownPlaceholders(): void
    {
        $template = (new EmailTemplate())
            ->setOwnerKey('tool:1')
            ->setTemplateKey(EmailTemplateKey::SubscriptionCancelled->value)
            ->setSubject('Dag {{ customerName }}')
            ->setBodyHtml('<p>Tot ziens {{ unknownField }}.</p>');
        $this->repository->method('findOneByOwnerAndKey')->willReturn($template);

        $result = $this->service->render('tool:1', EmailTemplateKey::SubscriptionCancelled, []);

        $this->assertSame('Dag ', $result['subject']);
        $this->assertStringContainsString('<p>Tot ziens .</p>', $result['html']);
        $this->assertStringNotContainsString('{{', $result['html']);
    }

    public function testRenderUsesOwnerOverrideAndBranding(): void
    {
        $template = (new EmailTemplate())
            ->setOwnerKey('tool:1')
            ->setTemplateKey(EmailTemplateKey::SubscriptionActivated->value)
            ->setSubject('Welkom bij {{ companyName }}')
            ->setBodyHtml('<p>Hoi!</p>');
        $this->repository->method('findOneByOwnerAndKey')->willReturn($template);

        $result = $this->service->render(
            'tool:1',
            EmailTemplateKey::SubscriptionActivated,
            ['companyName' => 'Acme BV', 'accentColor' => '#ff0000'],
        );

        $this->assertSame('Welkom bij Acme BV', $result['subject']);
        $this->assertStringContainsString('#ff0000', $result['html']);
        $this->assertStringContainsString('Acme BV', $result['html']);
        $this->assertStringContainsString('<p>Hoi!</p>', $result['html']);
    }

    public function testRenderShowsBothToolAndCompanyLogos(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);

        $result = $this->service->render(
            'tool:1',
            EmailTemplateKey::SubscriptionActivated,
            [
                'toolLogoUrl' => 'https://cdn.example/tool.png',
                'companyLogoUrl' => 'https://cdn.example/company.png',
                'companyName' => 'Acme',
            ],
        );

        $this->assertStringContainsString('<img src="https://cdn.example/tool.png"', $result['html']);
        $this->assertStringContainsString('<img src="https://cdn.example/company.png"', $result['html']);
    }

    public function testRenderBuildsLegalSignatureFromBranding(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);

        $result = $this->service->render(
            'tool:1',
            EmailTemplateKey::SubscriptionActivated,
            [
                'companyName' => 'Acme Facturatie B.V.',
                'address' => 'Dorpsstraat 1, 1234 AB Utrecht, Nederland',
                'cocNumber' => '12345678',
                'vatNumber' => 'NL0012.34.567.B01',
                'iban' => 'NL00 BANK 0123 4567 89',
            ],
        );

        $this->assertStringContainsString('Acme Facturatie B.V.', $result['html']);
        $this->assertStringContainsString('Dorpsstraat 1, 1234 AB Utrecht, Nederland', $result['html']);
        $this->assertStringContainsString('KvK 12345678', $result['html']);
        $this->assertStringContainsString('BTW NL0012.34.567.B01', $result['html']);
        $this->assertStringContainsString('IBAN NL00 BANK 0123 4567 89', $result['html']);
    }

    public function testHeaderPrefersToolNameOverCompany(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);

        $result = $this->service->render(
            'tool:1',
            EmailTemplateKey::SubscriptionActivated,
            ['toolName' => 'QonnectHub', 'companyName' => 'Van der Sangen B.V.'],
        );

        // Tool brand in the header; company still appears in the signature.
        $this->assertStringContainsString('QonnectHub', $result['html']);
        $this->assertStringContainsString('Van der Sangen B.V.', $result['html']);
    }

    public function testIsEnabledDefaultsTrueWithoutOverride(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);

        $this->assertTrue($this->service->isEnabled('tool:1', EmailTemplateKey::SubscriptionCancelled));
    }

    public function testIsEnabledFalseWhenOverrideDisabled(): void
    {
        $template = (new EmailTemplate())->setEnabled(false);
        $this->repository->method('findOneByOwnerAndKey')->willReturn($template);

        $this->assertFalse($this->service->isEnabled('tool:1', EmailTemplateKey::SubscriptionCancelled));
    }

    public function testUpdatePersistsPayload(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(EmailTemplate::class), true);

        $template = $this->service->update(
            'tool:1',
            EmailTemplateKey::SubscriptionCancelled,
            ['subject' => 'Aangepast', 'bodyHtml' => '<p>Body</p>', 'enabled' => false],
        );

        $this->assertSame('Aangepast', $template->getSubject());
        $this->assertSame('<p>Body</p>', $template->getBodyHtml());
        $this->assertFalse($template->isEnabled());
    }

    public function testUpdateBlankStringsBecomeNullFallback(): void
    {
        $this->repository->method('findOneByOwnerAndKey')->willReturn(null);
        $this->repository->method('save');

        $template = $this->service->update(
            'tool:1',
            EmailTemplateKey::SubscriptionCancelled,
            ['subject' => '   ', 'bodyHtml' => ''],
        );

        $this->assertNull($template->getSubject());
        $this->assertNull($template->getBodyHtml());
    }
}
