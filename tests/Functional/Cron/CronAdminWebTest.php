<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\Cron;

use VanDerSangen\ProjectTemplateBundle\Tests\Functional\SuperAdminLoginTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CronAdminWebTest extends WebTestCase
{
    use SuperAdminLoginTrait;

    private const string CRONS_PREFIX = '/super-admin/crons';

    public function testIndexReturnsListPage(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(Request::METHOD_GET, self::CRONS_PREFIX);
        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('cron', strtolower($content));
    }

    public function testNewReturnsForm(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(Request::METHOD_GET, self::CRONS_PREFIX . '/new');
        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('command', $content);
        $this->assertStringContainsString('schedule', $content);
    }

    public function testCreateRedirectsToList(): void
    {
        $client = $this->createSuperAdminClient();
        $crawler = $client->request(Request::METHOD_GET, self::CRONS_PREFIX . '/new');
        $form = $crawler->selectButton('Opslaan')->form([
            'name' => 'Web test job',
            'command' => 'list',
            'schedule' => '0 9 * * *',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Web test job', $content);
    }
}
