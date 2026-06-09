<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use RuntimeException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * PDF renderer backed by a Gotenberg service (headless Chromium) over HTTP.
 * No binary is baked into the PHP image; Gotenberg runs as a sidecar container
 * in docker-compose and Kubernetes. Implements the same contract as the
 * wkhtmltopdf-based renderer so the two are interchangeable.
 */
class GotenbergPdfRenderer implements PdfRendererInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl = 'http://gotenberg:3000',
    ) {
    }

    public function renderToString(string $html): string
    {
        $formData = new FormDataPart([
            'files' => new DataPart($html, 'index.html', 'text/html'),
        ]);

        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->baseUrl, '/') . '/forms/chromium/convert/html',
                [
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                    'body' => $formData->bodyToIterable(),
                ],
            );

            return $response->getContent();
        } catch (Throwable $e) {
            throw new RuntimeException('Gotenberg PDF rendering failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function renderToFile(string $html, string $outputPath): void
    {
        $bytes = $this->renderToString($html);
        if (file_put_contents($outputPath, $bytes) === false) {
            throw new RuntimeException(sprintf('Could not write PDF to %s', $outputPath));
        }
    }
}
