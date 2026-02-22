<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Ensure APP_ENV is set to 'test' for PHPUnit (phpunit.xml sets $_SERVER['APP_ENV'])
if (isset($_SERVER['APP_ENV'])) {
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
    putenv('APP_ENV=' . $_SERVER['APP_ENV']);
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');
}

// Set the KERNEL_CLASS for functional tests using WebTestCase
$_SERVER['KERNEL_CLASS'] = \VanDerSangen\ProjectTemplateBundle\Tests\App\TestKernel::class;

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
