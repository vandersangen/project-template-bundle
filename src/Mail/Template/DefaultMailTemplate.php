<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Template;

use RuntimeException;

class DefaultMailTemplate
{
    public const string BASE = 'base';
    public const string NOTIFICATION = 'notification';
    public const string PASSWORD_RESET = 'password_reset';
    public const string WELCOME = 'welcome';
    public const string PASSWORD_RESET_CONFIRMATION = 'password_reset_confirmation';

    private const string TEMPLATES_DIR = __DIR__ . '/templates';

    public static function getTemplatesDir(): string
    {
        return self::TEMPLATES_DIR;
    }

    public static function load(string $templateName): string
    {
        $filePath = self::TEMPLATES_DIR . '/' . $templateName . '.html.twig';

        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('Mail template "%s" not found at path: %s', $templateName, $filePath));
        }

        return file_get_contents($filePath);
    }
}
