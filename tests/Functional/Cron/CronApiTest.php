<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\Cron;

use VanDerSangen\ProjectTemplateBundle\Tests\Functional\SuperAdminLoginTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CronApiTest extends WebTestCase
{
    use SuperAdminLoginTrait;

    private const string API_PREFIX = '/super-admin/api/crons';

    public function testGetCronListEmpty(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(Request::METHOD_GET, self::API_PREFIX);
        $this->assertResponseIsSuccessful();
        $this->assertJson((string) $client->getResponse()->getContent());
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testCreateAndGetCron(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(
            Request::METHOD_POST,
            self::API_PREFIX,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test job',
                'command' => 'list',
                'schedule' => '0 9 * * *',
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Test job', $data['name']);
        $this->assertSame('list', $data['command']);
        $this->assertSame('0 9 * * *', $data['schedule']);
        $this->assertArrayHasKey('nextRunAt', $data);
        $id = $data['id'];

        $client->request(Request::METHOD_GET, self::API_PREFIX . '/' . $id);
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame($id, $data['id']);
        $this->assertSame('Test job', $data['name']);
    }

    public function testGetCronNotFound(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(Request::METHOD_GET, self::API_PREFIX . '/99999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCron(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(
            Request::METHOD_POST,
            self::API_PREFIX,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Original',
                'command' => 'list',
                'schedule' => '* * * * *',
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request(
            Request::METHOD_PUT,
            self::API_PREFIX . '/' . $id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Updated name',
                'command' => 'cache:clear',
                'schedule' => '0 10 * * *',
            ])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame('Updated name', $data['name']);
        $this->assertSame('cache:clear', $data['command']);
        $this->assertSame('0 10 * * *', $data['schedule']);
    }

    public function testDeleteCron(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(
            Request::METHOD_POST,
            self::API_PREFIX,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'To delete',
                'command' => 'list',
                'schedule' => '* * * * *',
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request(Request::METHOD_DELETE, self::API_PREFIX . '/' . $id);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request(Request::METHOD_GET, self::API_PREFIX . '/' . $id);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateCronWithOptionalFields(): void
    {
        $client = $this->createSuperAdminClient();
        $client->request(
            Request::METHOD_POST,
            self::API_PREFIX,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Full job',
                'command' => 'app:run',
                'schedule' => '0 8 * * *',
                'commandArguments' => ['--env' => 'prod'],
                'enabled' => false,
                'timezone' => 'Europe/Amsterdam',
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame(['--env' => 'prod'], $data['commandArguments']);
        $this->assertFalse($data['enabled']);
        $this->assertSame('Europe/Amsterdam', $data['timezone']);
    }
}
