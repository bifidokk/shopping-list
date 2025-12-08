<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->loadFixtures();
    }

    /**
     * @param array<string> $groups Fixture groups to load (default: ['test'])
     */
    protected function loadFixtures(array $groups = ['test']): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $application->setAutoExit(false);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--group' => $groups,
            '--no-interaction' => true,
            '--quiet' => true,
        ]);

        $output = new \Symfony\Component\Console\Output\NullOutput();
        $application->run($input, $output);
    }

    /**
     * @param array<string> $groups Specific fixture groups
     */
    protected function loadSpecificFixtures(array $groups): void
    {
        $this->loadFixtures($groups);
    }

    protected function makeAuthenticatedRequest(
        string $method,
        string $uri,
        array $data = [],
        int $telegramId = 123456789
    ): Response {
        $initData = $this->createMockInitData($telegramId);

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TELEGRAM_INIT_DATA' => $initData,
            ],
            empty($data) ? null : json_encode($data)
        );

        return $this->client->getResponse();
    }

    protected function createMockInitData(int $telegramId): string
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? 'test_bot_token_for_testing';

        $userData = json_encode([
            'id' => $telegramId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'language_code' => 'en',
        ]);

        $authDate = (string) time();

        $data = [
            'auth_date' => $authDate,
            'user' => $userData,
        ];

        ksort($data);

        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key.'='.$value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query([
            'auth_date' => $authDate,
            'user' => $userData,
            'hash' => $hash,
        ]);
    }

    protected function assertJsonResponse(Response $response, int $statusCode = 200): array
    {
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));

        $content = $response->getContent();
        $this->assertNotFalse($content);

        $data = json_decode($content, true);
        $this->assertIsArray($data);

        return $data;
    }
}
