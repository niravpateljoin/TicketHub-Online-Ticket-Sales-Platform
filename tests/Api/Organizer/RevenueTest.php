<?php

namespace App\Tests\Api\Organizer;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class RevenueTest extends ApiTestCase
{
    public function test_revenue_endpoint_returns_correct_fields(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');

        $data = $this->jsonRequest('GET', '/api/organizer/stats', token: $token);

        $this->assertJsonStatus(200);
        self::assertArrayHasKey('grossRevenue', $data['data']);
        self::assertArrayHasKey('systemFee', $data['data']);
        self::assertArrayHasKey('netRevenue', $data['data']);
        self::assertArrayHasKey('totalEvents', $data['data']);
    }

    public function test_revenue_calculation_is_correct(): void
    {
        // Create a confirmed booking with a known total
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_CONFIRMED)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);

        $booking = new Booking();
        $booking
            ->setUser($user)
            ->setEvent($event)
            ->setTotalCredits(505)   // base 500 + 1% fee = 505
            ->setStatus(Booking::STATUS_CONFIRMED)
            ->setIdempotencyKey(bin2hex(random_bytes(8)));
        $this->em->persist($booking);
        $this->em->flush();

        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $data = $this->jsonRequest('GET', '/api/organizer/stats', token: $token);

        $this->assertJsonStatus(200);
        self::assertSame(505, $data['data']['grossRevenue']);
        // netRevenue = round(505 * 0.99) = 500
        self::assertSame(500, $data['data']['netRevenue']);
        // systemFee = 505 - 500 = 5
        self::assertSame(5, $data['data']['systemFee']);
    }

    public function test_organizer_cannot_see_another_organizers_revenue(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');

        $this->jsonRequest('GET', '/api/organizer/stats', token: $token);

        $this->assertJsonStatus(403);
    }

    public function test_revenue_inaccessible_without_auth(): void
    {
        $this->jsonRequest('GET', '/api/organizer/stats');
        $this->assertJsonStatus(401);
    }
}
