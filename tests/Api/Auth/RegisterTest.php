<?php

namespace App\Tests\Api\Auth;

use App\Entity\Organizer;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class RegisterTest extends ApiTestCase
{
    public function test_user_registration_returns_201_and_unverified_payload(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'new-user@test.local',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(201);
        self::assertSame('new-user@test.local', $data['data']['email'] ?? null);
        self::assertFalse((bool) ($data['data']['isVerified'] ?? true));
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => TestFixtures::USER_EMAIL,
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(422);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('email', $data['errors']);
    }

    public function test_registration_fails_with_short_password(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'short-pass@test.local',
            'password' => 'short',
        ]);

        $this->assertJsonStatus(422);
        self::assertArrayHasKey('password', $data['errors'] ?? []);
    }

    public function test_registration_fails_with_invalid_email_format(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(422);
        self::assertArrayHasKey('email', $data['errors'] ?? []);
    }

    public function test_organizer_registration_returns_pending_status_and_creates_approval_record(): void
    {
        $this->jsonRequest('POST', '/api/auth/register/organizer', [
            'email' => 'new-organizer@test.local',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(201);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'new-organizer@test.local']);
        self::assertNotNull($user);

        $organizer = $this->em->getRepository(Organizer::class)->findOneBy(['user' => $user]);
        self::assertNotNull($organizer);
        self::assertSame(Organizer::STATUS_PENDING, $organizer->getApprovalStatus());
    }
}

