<?php

namespace App\Tests\Api\Organizer;

use App\Entity\Event;
use App\Entity\TicketTier;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class TierCrudTest extends ApiTestCase
{
    public function test_organizer_can_add_tier_to_own_event(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $data = $this->jsonRequest(
            'POST',
            '/api/organizer/events/'.$event->getId().'/tiers',
            ['name' => 'VIP', 'price' => '1000', 'totalSeats' => '50'],
            $token
        );

        $this->assertJsonStatus(201);
        self::assertSame('VIP', $data['data']['name'] ?? null);
        self::assertSame(1000, $data['data']['basePrice'] ?? null);
    }

    public function test_organizer_can_edit_tier(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $data = $this->jsonRequest(
            'PUT',
            '/api/organizer/events/'.$event->getId().'/tiers/'.$tier->getId(),
            ['name' => 'General Updated', 'price' => '550', 'totalSeats' => '10'],
            $token
        );

        $this->assertJsonStatus(200);
        self::assertSame('General Updated', $data['data']['name'] ?? null);
        self::assertSame(550, $data['data']['basePrice'] ?? null);
    }

    public function test_organizer_cannot_delete_tier_with_sold_tickets(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'php-summit-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_SOLD_OUT]);

        // soldCount = 1 in fixtures
        $this->jsonRequest('DELETE', '/api/organizer/events/'.$event->getId().'/tiers/'.$tier->getId(), token: $token);

        $this->assertJsonStatus(409);
    }

    public function test_tier_creation_fails_without_required_fields(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $data = $this->jsonRequest(
            'POST',
            '/api/organizer/events/'.$event->getId().'/tiers',
            ['name' => 'Incomplete'],
            $token
        );

        $this->assertJsonStatus(422);
        self::assertArrayHasKey('errors', $data);
    }
}
