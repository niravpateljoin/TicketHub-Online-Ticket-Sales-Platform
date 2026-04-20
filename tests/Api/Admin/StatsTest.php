<?php

namespace App\Tests\Api\Admin;

use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class StatsTest extends ApiTestCase
{
    public function test_admin_stats_returns_correct_counts(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $data = $this->jsonRequest('GET', '/api/admin/stats', token: $token);

        $this->assertJsonStatus(200);
        self::assertArrayHasKey('totalUsers', $data['data']);
        self::assertArrayHasKey('totalEvents', $data['data']);
        self::assertArrayHasKey('totalTicketsSold', $data['data']);
        self::assertArrayHasKey('totalSystemRevenue', $data['data']);

        // Fixtures seed 3 regular users
        self::assertSame(3, $data['data']['totalUsers']);
        // Fixtures seed 3 events (Rock Night, PHP Summit, Cancelled)
        self::assertSame(3, $data['data']['totalEvents']);
        // No confirmed bookings in fixtures
        self::assertSame(0, $data['data']['totalTicketsSold']);
        self::assertSame(0, $data['data']['totalSystemRevenue']);
    }

    public function test_stats_inaccessible_to_regular_user(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');

        $this->jsonRequest('GET', '/api/admin/stats', token: $token);

        $this->assertJsonStatus(403);
    }

    public function test_stats_inaccessible_without_auth(): void
    {
        $this->jsonRequest('GET', '/api/admin/stats');
        $this->assertJsonStatus(401);
    }
}
