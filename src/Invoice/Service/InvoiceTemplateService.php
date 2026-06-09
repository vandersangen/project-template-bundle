<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use DateTimeImmutable;
use VanDerSangen\ProjectTemplateBundle\Invoice\Dto\PartyData;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceTemplate;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceTemplateRepository;

/**
 * Manages per-owner invoice templates and resolves the effective issuer party
 * (template fields, falling back to platform defaults field by field).
 */
class InvoiceTemplateService
{
    public function __construct(
        private readonly InvoiceTemplateRepository $repository,
    ) {
    }

    public function find(string $ownerKey): ?InvoiceTemplate
    {
        return $this->repository->findOneByOwnerKey($ownerKey);
    }

    public function getOrCreate(string $ownerKey): InvoiceTemplate
    {
        $template = $this->repository->findOneByOwnerKey($ownerKey);
        if ($template === null) {
            $template = new InvoiceTemplate();
            $template->setOwnerKey($ownerKey);
        }
        return $template;
    }

    /**
     * Applies an admin payload to the template (only keys present are touched)
     * and persists it.
     *
     * @param string               $ownerKey
     * @param array<string, mixed> $data
     */
    public function update(string $ownerKey, array $data): InvoiceTemplate
    {
        $template = $this->getOrCreate($ownerKey);

        if (array_key_exists('enabled', $data)) {
            $template->setEnabled((bool) $data['enabled']);
        }

        foreach (self::stringFields() as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key] === null ? null : trim((string) $data[$key]);
                $template->{$setter}($value === '' ? null : $value);
            }
        }

        if (array_key_exists('vatRate', $data)) {
            $template->setVatRate((int) $data['vatRate']);
        }
        if (array_key_exists('vatMode', $data) && $data['vatMode'] !== null && $data['vatMode'] !== '') {
            $template->setVatMode(VatMode::from((string) $data['vatMode']));
        }

        if ($template->getId() !== null) {
            $template->setUpdatedAt(new DateTimeImmutable());
        }

        $this->repository->save($template, true);

        return $template;
    }

    public function setLogoPath(string $ownerKey, string $logoPath): InvoiceTemplate
    {
        $template = $this->getOrCreate($ownerKey);
        $template->setLogoPath($logoPath);
        if ($template->getId() !== null) {
            $template->setUpdatedAt(new DateTimeImmutable());
        }
        $this->repository->save($template, true);
        return $template;
    }

    /**
     * Effective issuer: template field if set, otherwise the platform fallback.
     */
    public function resolveIssuer(?InvoiceTemplate $template, PartyData $fallback): PartyData
    {
        if ($template === null) {
            return $fallback;
        }

        return new PartyData(
            name: $template->getCompanyName() ?? $fallback->name,
            companyName: $template->getCompanyName() ?? $fallback->companyName,
            email: $template->getEmail() ?? $fallback->email,
            vatNumber: $template->getVatNumber() ?? $fallback->vatNumber,
            cocNumber: $template->getCocNumber() ?? $fallback->cocNumber,
            iban: $template->getIban() ?? $fallback->iban,
            street: $template->getStreet() ?? $fallback->street,
            houseNumber: $template->getHouseNumber() ?? $fallback->houseNumber,
            postalCode: $template->getPostalCode() ?? $fallback->postalCode,
            city: $template->getCity() ?? $fallback->city,
            country: $template->getCountry() ?? $fallback->country,
            logoPath: $template->getLogoPath() ?? $fallback->logoPath,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function stringFields(): array
    {
        return [
            'logoPath' => 'setLogoPath',
            'companyName' => 'setCompanyName',
            'street' => 'setStreet',
            'houseNumber' => 'setHouseNumber',
            'postalCode' => 'setPostalCode',
            'city' => 'setCity',
            'country' => 'setCountry',
            'vatNumber' => 'setVatNumber',
            'cocNumber' => 'setCocNumber',
            'iban' => 'setIban',
            'email' => 'setEmail',
            'footerText' => 'setFooterText',
            'accentColor' => 'setAccentColor',
            'numberPrefix' => 'setNumberPrefix',
        ];
    }
}
