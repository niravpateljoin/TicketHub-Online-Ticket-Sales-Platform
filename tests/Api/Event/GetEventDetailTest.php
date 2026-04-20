<?php

namespace App\Tests\Api\Event;

use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class GetEventDetailTest extends ApiTestCase
{
    public function test_event_detail_returns_tiers_with_available_seats(): void
    {
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $data = $this->jsonRequest('GET', '/api/events/'.$event->getId());

        $this->assertJsonStatus(200);
        self::assertIsArray($data['data']['tiers'] ?? null);
        self::assertNotEmpty($data['data']['tiers']);

        $tierNames = array_map(static fn (array $t): string => (string) ($t['name'] ?? ''), $data['data']['tiers']);
        self::assertContains(TestFixtures::TIER_GENERAL, $tierNames);
    }

    public function test_event_detail_returns_404_for_nonexistent_event(): void
    {
        $this->jsonRequest('GET', '/api/events/99999');
        $this->assertJsonStatus(404);
    }

    public function test_tier_available_seats_calculated_correctly(): void
    {
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        // Create an active reservation — should reduce available seats
        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(3)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);
        $this->em->flush();

        $data = $this->jsonRequest('GET', '/api/events/'.$event->getId());
        $this->assertJsonStatus(200);

        $tierData = null;
        foreach ($data['data']['tiers'] ?? [] as $t) {
            if ($t['name'] === TestFixtures::TIER_GENERAL) {
                $tierData = $t;
                break;
            }
        }

        self::assertNotNull($tierData);
        // totalSeats = 10, soldCount = 0, activeReservations = 3 → available = 7
        self::assertSame(7, $tierData['availableSeats']);
    }

    public function test_cancelled_event_detail_still_accessible(): void
    {
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'cancelled-test-event']);

        $data = $this->jsonRequest('GET', '/api/events/'.$event->getId());

        $this->assertJsonStatus(200);
        self::assertSame('cancelled', $data['data']['status'] ?? null);
    }

    public function test_event_detail_accessible_without_auth(): void
    {
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $this->jsonRequest('GET', '/api/events/'.$event->getId());
        $this->assertJsonStatus(200);
    }
}
