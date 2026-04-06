<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tenant\Exception;

class TenantOwnerProtectionException extends \DomainException
{
    public static function cannotDeleteOwner(int $userId, int $tenantId): self
    {
        return new self(sprintf(
            'User %d is the owner of tenant %d and cannot be deleted'
            . ' or deactivated without cancelling the subscription first.',
            $userId,
            $tenantId,
        ));
    }
}
