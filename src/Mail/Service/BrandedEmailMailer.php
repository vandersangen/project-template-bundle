<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Service;

use Psr\Log\LoggerInterface;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceTemplate;
use VanDerSangen\ProjectTemplateBundle\Invoice\Service\InvoiceTemplateService;
use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\EmailTemplateKey;

/**
 * Generic entry point for sending a branded, per-owner lifecycle e-mail. Reusable
 * by any project-template consumer: the caller passes an owner key, a template
 * key, recipients and a flat data context; branding is resolved from the owner's
 * {@see InvoiceTemplate} (single source of truth, shared with invoices) and can
 * be overridden per call (e.g. an absolute logo URL the caller alone can build).
 *
 * The mail is queued via {@see MailService} with the owner's own from-address, so
 * it reads as coming from that tool rather than a single platform sender.
 */
class BrandedEmailMailer
{
    public function __construct(
        private readonly EmailTemplateService $templateService,
        private readonly InvoiceTemplateService $invoiceTemplateService,
        private readonly MailService $mailService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * $data carries per-event placeholders (nextBillingDate, endsAt, ...);
     * $brandingOverrides are merged over the resolved branding (e.g. logoUrl).
     *
     * @param string                     $ownerKey
     * @param EmailTemplateKey           $key
     * @param array<int, string>         $recipients
     * @param array<string, scalar|null> $data
     * @param array<string, scalar|null> $brandingOverrides
     */
    public function send(
        string $ownerKey,
        EmailTemplateKey $key,
        array $recipients,
        array $data = [],
        array $brandingOverrides = [],
    ): ?Mail {
        if ($recipients === []) {
            return null;
        }

        if (!$this->templateService->isEnabled($ownerKey, $key)) {
            $this->logger->info('Lifecycle mail suppressed (template disabled)', [
                'ownerKey' => $ownerKey,
                'templateKey' => $key->value,
            ]);
            return null;
        }

        $invoiceTemplate = $this->invoiceTemplateService->find($ownerKey);
        $branding = array_merge($this->brandingContext($invoiceTemplate), $brandingOverrides);

        $rendered = $this->templateService->render($ownerKey, $key, array_merge($branding, $data));

        $sender = $this->stringOrNull($brandingOverrides['senderEmail'] ?? null)
            ?? $invoiceTemplate?->getEmail();

        return $this->mailService->createAndSend(
            $rendered['subject'],
            $rendered['html'],
            $recipients,
            $sender,
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function brandingContext(?InvoiceTemplate $template): array
    {
        return [
            // The product's own brand (header). Only the caller can resolve these,
            // so they default empty and fall back to the company name.
            'toolName' => '',
            'toolLogoUrl' => '',
            // The issuing/billing entity (signature). Company details come from the
            // InvoiceTemplate; the logo URL must be built by the caller.
            'companyName' => $template?->getCompanyName() ?? '',
            'companyLogoUrl' => '',
            'companyEmail' => $template?->getEmail() ?? '',
            'accentColor' => $template?->getAccentColor() ?? '',
            'footerText' => $template?->getFooterText() ?? '',
            'vatNumber' => $template?->getVatNumber() ?? '',
            'cocNumber' => $template?->getCocNumber() ?? '',
            'iban' => $template?->getIban() ?? '',
            'address' => $this->formatAddress($template),
        ];
    }

    private function formatAddress(?InvoiceTemplate $t): string
    {
        if ($t === null) {
            return '';
        }

        $street = trim(implode(' ', array_filter([$t->getStreet(), $t->getHouseNumber()])));
        $city = trim(implode(' ', array_filter([$t->getPostalCode(), $t->getCity()])));

        return trim(implode(', ', array_filter([$street, $city, $t->getCountry()])));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
