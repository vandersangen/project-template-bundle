<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Service;

use DateTimeImmutable;
use VanDerSangen\ProjectTemplateBundle\Mail\Entity\EmailTemplate;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\EmailTemplateKey;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\EmailTemplateRepository;

/**
 * Manages per-owner lifecycle e-mail templates and renders them: it resolves the
 * effective subject/body (owner override, falling back to the enum default),
 * wraps the body in a branded shell, and fills {{ placeholders }} from the
 * render context. The context is a flat map of branding + per-event values.
 */
class EmailTemplateService
{
    private const string DEFAULT_ACCENT = '#1f3a5f';

    public function __construct(
        private readonly EmailTemplateRepository $repository,
    ) {
    }

    public function find(string $ownerKey, EmailTemplateKey $key): ?EmailTemplate
    {
        return $this->repository->findOneByOwnerAndKey($ownerKey, $key->value);
    }

    /**
     * @return EmailTemplate[]
     */
    public function findAllByOwner(string $ownerKey): array
    {
        return $this->repository->findByOwnerKey($ownerKey);
    }

    public function getOrCreate(string $ownerKey, EmailTemplateKey $key): EmailTemplate
    {
        $template = $this->find($ownerKey, $key);
        if ($template === null) {
            $template = new EmailTemplate();
            $template->setOwnerKey($ownerKey);
            $template->setTemplateKey($key->value);
        }
        return $template;
    }

    /**
     * Applies an admin payload (only present keys are touched) and persists it.
     *
     * @param string                $ownerKey
     * @param EmailTemplateKey      $key
     * @param array<string, mixed>  $data
     */
    public function update(string $ownerKey, EmailTemplateKey $key, array $data): EmailTemplate
    {
        $template = $this->getOrCreate($ownerKey, $key);

        if (array_key_exists('subject', $data)) {
            $value = $data['subject'] === null ? null : trim((string) $data['subject']);
            $template->setSubject($value === '' ? null : $value);
        }
        if (array_key_exists('bodyHtml', $data)) {
            $value = $data['bodyHtml'] === null ? null : (string) $data['bodyHtml'];
            $template->setBodyHtml($value === '' ? null : $value);
        }
        if (array_key_exists('enabled', $data)) {
            $template->setEnabled((bool) $data['enabled']);
        }

        if ($template->getId() !== null) {
            $template->setUpdatedAt(new DateTimeImmutable());
        }

        $this->repository->save($template, true);

        return $template;
    }

    /**
     * Whether the mail should be sent for this owner (a stored, disabled override
     * suppresses it; absence means "send with defaults").
     */
    public function isEnabled(string $ownerKey, EmailTemplateKey $key): bool
    {
        return $this->find($ownerKey, $key)?->isEnabled() ?? true;
    }

    /**
     * Renders the effective mail for an owner (branding + per-event context).
     *
     * @param string                     $ownerKey
     * @param EmailTemplateKey           $key
     * @param array<string, scalar|null> $context
     *
     * @return array{subject: string, html: string}
     */
    public function render(string $ownerKey, EmailTemplateKey $key, array $context): array
    {
        $template = $this->find($ownerKey, $key);

        $subject = $template?->getSubject() ?? $key->defaultSubject();
        $inner = $template?->getBodyHtml() ?? $key->defaultBodyHtml();

        return [
            'subject' => $this->replacePlaceholders($subject, $context),
            'html' => $this->wrap($this->replacePlaceholders($inner, $context), $context),
        ];
    }

    /**
     * Wraps rendered inner HTML in a branded, e-mail-client-safe shell.
     *
     * @param string                     $innerHtml
     * @param array<string, scalar|null> $context
     */
    private function wrap(string $innerHtml, array $context): string
    {
        $accent = $this->stringValue($context, 'accentColor') ?: self::DEFAULT_ACCENT;
        $companyName = $this->stringValue($context, 'companyName');
        $toolName = $this->stringValue($context, 'toolName');
        $toolLogoUrl = $this->stringValue($context, 'toolLogoUrl');
        $companyLogoUrl = $this->stringValue($context, 'companyLogoUrl');
        $address = $this->stringValue($context, 'address');
        $footerText = $this->stringValue($context, 'footerText');

        return '<!doctype html><html lang="nl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#eef0f3;'
            . 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2733">'
            . '<div style="max-width:600px;margin:0 auto;padding:28px 12px">'
            . '<div style="background:#ffffff;border:1px solid #dfe3e8;border-radius:4px;overflow:hidden">'
            // Tool header: the product's own brand.
            . '<div style="padding:22px 32px;border-bottom:3px solid ' . htmlspecialchars($accent, ENT_QUOTES) . '">'
            . $this->headerBrand($toolLogoUrl, $toolName !== '' ? $toolName : $companyName, $accent)
            . '</div>'
            // Body.
            . '<div style="padding:30px 32px;font-size:15px;line-height:1.65;color:#1f2733">'
            . $innerHtml . '</div>'
            // Company signature: the issuing/billing entity, with legal identity.
            . '<div style="padding:22px 32px;border-top:1px solid #e6e9ee;background:#f7f8fa">'
            . $this->companySignature($companyLogoUrl, $companyName, $address, $context)
            . '</div>'
            . '</div>'
            . ($footerText !== ''
                ? '<div style="max-width:600px;margin:12px auto 0;padding:0 32px;'
                    . 'color:#9aa4b0;font-size:11px;line-height:1.5;text-align:center">'
                    . htmlspecialchars($footerText, ENT_QUOTES) . '</div>'
                : '')
            . '</div></body></html>';
    }

    private function headerBrand(string $logoUrl, string $name, string $accent): string
    {
        if ($logoUrl !== '') {
            return '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES) . '" alt="'
                . htmlspecialchars($name, ENT_QUOTES) . '" style="max-height:40px;border:0;display:block">';
        }

        return '<span style="font-size:19px;font-weight:700;letter-spacing:-.01em;color:'
            . htmlspecialchars($accent, ENT_QUOTES) . '">' . htmlspecialchars($name, ENT_QUOTES) . '</span>';
    }

    /**
     * @param string                     $logoUrl
     * @param string                     $companyName
     * @param string                     $address
     * @param array<string, scalar|null> $context
     */
    private function companySignature(string $logoUrl, string $companyName, string $address, array $context): string
    {
        $legalParts = array_filter([
            ($v = $this->stringValue($context, 'cocNumber')) !== '' ? 'KvK ' . $v : null,
            ($v = $this->stringValue($context, 'vatNumber')) !== '' ? 'BTW ' . $v : null,
            ($v = $this->stringValue($context, 'iban')) !== '' ? 'IBAN ' . $v : null,
        ]);

        $lines = '<div style="font-weight:600;color:#3a4655;font-size:13px">'
            . htmlspecialchars($companyName, ENT_QUOTES) . '</div>';
        if ($address !== '') {
            $lines .= '<div>' . htmlspecialchars($address, ENT_QUOTES) . '</div>';
        }
        if ($legalParts !== []) {
            $lines .= '<div>' . htmlspecialchars(implode(' · ', $legalParts), ENT_QUOTES) . '</div>';
        }

        $logoCell = $logoUrl !== ''
            ? '<td style="vertical-align:top;padding-right:14px;width:1%;white-space:nowrap">'
                . '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES) . '" alt="'
                . htmlspecialchars($companyName, ENT_QUOTES)
                . '" style="max-height:30px;border:0;display:block"></td>'
            : '';

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . $logoCell
            . '<td style="vertical-align:top;color:#6b7683;font-size:12px;line-height:1.5">' . $lines . '</td>'
            . '</tr></table>';
    }

    /**
     * Replaces {{ name }} / {{name}} tokens with context values; unknown tokens
     * are stripped so no raw placeholders leak into the mail.
     *
     * @param string                     $text
     * @param array<string, scalar|null> $context
     */
    private function replacePlaceholders(string $text, array $context): string
    {
        $result = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static fn (array $m): string => array_key_exists($m[1], $context) ? (string) $context[$m[1]] : '',
            $text,
        );

        return $result ?? $text;
    }

    /**
     * @param array<string, scalar|null> $context
     * @param string                     $key
     */
    private function stringValue(array $context, string $key): string
    {
        return array_key_exists($key, $context) && $context[$key] !== null ? (string) $context[$key] : '';
    }
}
