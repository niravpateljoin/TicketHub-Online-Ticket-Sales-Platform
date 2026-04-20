<?php

namespace App\Tests;

use App\Tests\Fixtures\TestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Doctrine\DBAL\Platforms\SqlitePlatform;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $this->resetDatabase();
        $this->clearRateLimiterStore();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        TestFixtures::seed($this->em, $hasher);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->clear();
    }

    protected function loginAs(string $email, string $password): string
    {
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('token', $data);

        return $data['token'];
    }

    protected function jsonRequest(string $method, string $url, array $body = [], string $token = ''): array
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($token !== '') {
            $server['HTTP_AUTHORIZATION'] = sprintf('Bearer %s', $token);
        }

        $content = $body !== [] ? json_encode($body, JSON_THROW_ON_ERROR) : null;
        $this->client->request($method, $url, [], [], $server, $content);

        $raw = (string) $this->client->getResponse()->getContent();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function assertJsonStatus(int $expected): void
    {
        self::assertSame($expected, $this->client->getResponse()->getStatusCode());
    }

    private function resetDatabase(): void
    {
        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();
        $tables = $connection->createSchemaManager()->listTableNames();

        if ($tables === []) {
            return;
        }

        if ($platform instanceof SqlitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $connection->executeStatement(sprintf('DELETE FROM "%s"', $table));
            }
            $connection->executeStatement('PRAGMA foreign_keys = ON');
            return;
        }

        $quoted = array_map(static fn (string $table): string => sprintf('"%s"', $table), $tables);
        $connection->executeStatement('TRUNCATE TABLE '.implode(', ', $quoted).' RESTART IDENTITY CASCADE');
    }

    private function clearRateLimiterStore(): void
    {
        if (!static::getContainer()->has('cache.rate_limiter')) {
            return;
        }

        /** @var CacheInterface $pool */
        $pool = static::getContainer()->get('cache.rate_limiter');
        if (method_exists($pool, 'clear')) {
            $pool->clear();
        }
    }
}
