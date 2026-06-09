<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use Knp\Snappy\Pdf;

/**
 * PDF renderer backed by wkhtmltopdf via knplabs/knp-snappy. The binary path is
 * injected so it can differ per environment/container.
 */
class SnappyPdfRenderer implements PdfRendererInterface
{
    /** @var array<string, mixed> */
    private array $options;

    private ?Pdf $snappy = null;

    public function __construct(
        private readonly string $binaryPath = '/usr/bin/wkhtmltopdf',
    ) {
        $this->options = [
            'enable-local-file-access' => true,
            'encoding' => 'UTF-8',
            'margin-top' => 12,
            'margin-bottom' => 12,
            'margin-left' => 12,
            'margin-right' => 12,
        ];
    }

    public function renderToString(string $html): string
    {
        return $this->snappy()->getOutputFromHtml($html, $this->options);
    }

    public function renderToFile(string $html, string $outputPath): void
    {
        $this->snappy()->generateFromHtml($html, $outputPath, $this->options, true);
    }

    private function snappy(): Pdf
    {
        if ($this->snappy === null) {
            $this->snappy = new Pdf($this->binaryPath);
        }
        return $this->snappy;
    }
}
