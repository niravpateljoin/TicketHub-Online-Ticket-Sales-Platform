<?php

namespace App\Tests\Api\Cart;

use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class RemoveFromCartTest extends ApiTestCase
{
    public function test_user_can_remove_own_cart_item(): void
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

        $data = $this->jsonRequest('DELETE', '/api/cart/'.$reservation->getId(), token: $token);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $updated = $this->em->getRepository(SeatReservation::class)->find($reservation->getId());
        self::assertSame(SeatReservation::STATUS_EXPIRED, $updated?->getStatus());
    }

    public function test_user_cannot_remove_another_users_cart_item(): void
    {
        // User1 adds to cart
        $token1 = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token1);

        $reservation = $this->em->getRepository(SeatReservation::class)->findOneBy([
            'ticketTier' => $tier,
            'status' => SeatReservation::STATUS_PENDING,
        ]);
        self::assertNotNull($reservation);

        // User2 tries to delete it
        $token2 = $this->loginAs(TestFixtures::USER2_EMAIL, 'password123');
        $this->jsonRequest('DELETE', '/api/cart/'.$reservation->getId(), token: $token2);

        $this->assertJsonStatus(404);
    }

    public function test_remove_requires_auth(): void
    {
        $this->jsonRequest('DELETE', '/api/cart/1');
        $this->assertJsonStatus(401);
    }
}
