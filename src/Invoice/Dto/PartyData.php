<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Dto;

/**
 * A party on an invoice (issuer or customer). All fields optional so it can
 * represent both a fully detailed issuer and a partial customer snapshot
 * (e.g. when a payment provider only returns a name and e-mail).
 */
final readonly class PartyData
{
    public function __construct(
        public ?string $name = null,
        public ?string $companyName = null,
        public ?string $email = null,
        public ?string $vatNumber = null,
        public ?string $cocNumber = null,
        public ?string $iban = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $logoPath = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: self::str($data, 'name'),
            companyName: self::str($data, 'companyName'),
            email: self::str($data, 'email'),
            vatNumber: self::str($data, 'vatNumber'),
            cocNumber: self::str($data, 'cocNumber'),
            iban: self::str($data, 'iban'),
            street: self::str($data, 'street'),
            houseNumber: self::str($data, 'houseNumber'),
            postalCode: self::str($data, 'postalCode'),
            city: self::str($data, 'city'),
            country: self::str($data, 'country'),
            logoPath: self::str($data, 'logoPath'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'companyName' => $this->companyName,
            'email' => $this->email,
            'vatNumber' => $this->vatNumber,
            'cocNumber' => $this->cocNumber,
            'iban' => $this->iban,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'logoPath' => $this->logoPath,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
