<?php

namespace App\Tests\Api\Auth;

use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class LoginTest extends ApiTestCase
{
    public function test_login_returns_jwt_and_user_data(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => TestFixtures::USER_EMAIL,
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(200);
        self::assertNotEmpty($data['token'] ?? null);
        self::assertSame(TestFixtures::USER_EMAIL, $data['user']['email'] ?? null);
        self::assertSame(2000, $data['user']['creditBalance'] ?? null);
        self::assertContains('ROLE_USER', $data['user']['roles'] ?? []);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => TestFixtures::USER_EMAIL,
            'password' => 'wrong-password',
        ]);

        $this->assertJsonStatus(401);
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'unknown@test.local',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(401);
    }
}

