<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Exception;

use RuntimeException;

class ShopifyApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isAuthenticationError(): bool
    {
        return in_array($this->statusCode, [401, 403], true);
    }
}
