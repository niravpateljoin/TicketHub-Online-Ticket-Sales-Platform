<?php

namespace App\Tests\Api\Auth;

use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class MeTest extends ApiTestCase
{
    public function test_me_returns_authenticated_user(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');

        $data = $this->jsonRequest('GET', '/api/auth/me', token: $token);

        $this->assertJsonStatus(200);
        self::assertSame(TestFixtures::USER_EMAIL, $data['data']['email'] ?? null);
        self::assertSame(2000, $data['data']['creditBalance'] ?? null);
        self::assertContains('ROLE_USER', $data['data']['roles'] ?? []);
    }

    public function test_me_returns_401_without_token(): void
    {
        $this->jsonRequest('GET', '/api/auth/me');
        $this->assertJsonStatus(401);
    }
}

