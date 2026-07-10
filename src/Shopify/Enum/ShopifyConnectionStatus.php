<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Enum;

enum ShopifyConnectionStatus: string
{
    case CONNECTED = 'connected';
    case ERROR = 'error';
}
