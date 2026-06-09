<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

/**
 * Abstraction over PDF generation so the binary-backed implementation can be
 * swapped for a stub in tests.
 */
interface PdfRendererInterface
{
    public function renderToString(string $html): string;

    public function renderToFile(string $html, string $outputPath): void;
}
