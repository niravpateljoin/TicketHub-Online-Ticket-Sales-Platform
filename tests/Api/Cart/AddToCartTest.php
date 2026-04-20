<?php

namespace App\Tests\Api\Cart;

use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class AddToCartTest extends ApiTestCase
{
    public function test_user_can_add_ticket_to_cart(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $data = $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $this->assertJsonStatus(201);
        self::assertNotEmpty($data['data']['items'] ?? []);

        $reservation = $this->em->getRepository(SeatReservation::class)->findOneBy([
            'ticketTier' => $tier,
            'status' => SeatReservation::STATUS_PENDING,
        ]);
        self::assertNotNull($reservation);
    }

    public function test_reservation_expires_at_is_10_minutes_from_now(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $this->assertJsonStatus(201);

        $reservation = $this->em->getRepository(SeatReservation::class)->findOneBy([
            'ticketTier' => $tier,
            'status' => SeatReservation::STATUS_PENDING,
        ]);
        self::assertNotNull($reservation);

        $expectedExpiry = (new \DateTime('+10 minutes'))->getTimestamp();
        $actualExpiry = $reservation->getExpiresAt()->getTimestamp();
        // Allow 5-second window for test execution time
        self::assertLessThanOrEqual(5, abs($expectedExpiry - $actualExpiry));
    }

    public function test_cannot_add_to_cart_when_no_seats_available(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_SOLD_OUT]);

        $data = $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $this->assertJsonStatus(409);
    }

    public function test_cannot_add_to_cart_before_sale_starts(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_FLASH_UPCOMING]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $this->assertJsonStatus(409);
    }

    public function test_cannot_add_to_cart_after_sale_ends(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_FLASH_CLOSED]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $this->assertJsonStatus(409);
    }

    public function test_cannot_add_to_cart_for_cancelled_event(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => 'Cancelled Tier']);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $this->assertJsonStatus(409);
    }

    public function test_unauthenticated_user_cannot_add_to_cart(): void
    {
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1']);

        $this->assertJsonStatus(401);
    }
}
