<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SampleTest extends TestCase
{
    public function testTrueIsTrue(): void
    {
        $this->assertTrue(true);
    }

    public function testAddition(): void
    {
        $this->assertEquals(4, 2 + 2);
    }
}
