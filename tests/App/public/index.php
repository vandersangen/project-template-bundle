<?php

use VanDerSangen\ProjectTemplateBundle\Tests\App\TestKernel;

require_once dirname(__DIR__, 2) . '/vendor/autoload_runtime.php';

return function (array $context) {
    return new TestKernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

