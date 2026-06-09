<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use Twig\Environment;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\Invoice;

/**
 * Renders an Invoice to HTML using the bundle's default Twig template. The
 * template ships with inline CSS so it renders identically under wkhtmltopdf.
 */
class InvoiceRenderer
{
    public const string DEFAULT_TEMPLATE = '@ProjectTemplateBundle/invoice/invoice.html.twig';

    public function __construct(
        private readonly Environment $twig,
        private readonly string $template = self::DEFAULT_TEMPLATE,
    ) {
    }

    public function render(Invoice $invoice): string
    {
        return $this->twig->render($this->template, ['invoice' => $invoice]);
    }
}
