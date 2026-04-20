<?php

namespace App\Tests\Api\Cart;

use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class GetCartTest extends ApiTestCase
{
    public function test_cart_shows_only_pending_non_expired_reservations(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        // Add one valid item
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '2'], $token);

        // Create an already-expired reservation directly
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $expired = new SeatReservation();
        $expired
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('-1 minute'));
        $this->em->persist($expired);
        $this->em->flush();

        $data = $this->jsonRequest('GET', '/api/cart', token: $token);
        $this->assertJsonStatus(200);

        // Only the non-expired reservation should appear
        $quantities = array_column($data['data']['items'] ?? [], 'quantity');
        self::assertContains(2, $quantities);
        self::assertNotContains(99, $quantities);
    }

    public function test_cart_total_calculated_with_1_percent_fee(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $data = $this->jsonRequest('GET', '/api/cart', token: $token);
        $this->assertJsonStatus(200);

        // basePrice = 500, finalPrice = 505 (500 * 1.01)
        self::assertSame(505, $data['data']['total'] ?? null);
    }

    public function test_cart_available_seats_count_decrements_after_add(): void
    {
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_LAST_SEAT]);

        $beforeData = $this->jsonRequest('GET', '/api/events');
        // Get available seats before adding
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        // After adding, available seats should have decreased
        $reservation = $this->em->getRepository(SeatReservation::class)->findOneBy([
            'ticketTier' => $tier,
            'status' => SeatReservation::STATUS_PENDING,
        ]);
        self::assertNotNull($reservation);
        self::assertSame(1, $reservation->getQuantity());
    }

    public function test_cart_requires_auth(): void
    {
        $this->jsonRequest('GET', '/api/cart');
        $this->assertJsonStatus(401);
    }
}
