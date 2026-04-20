<?php

namespace App\Tests\Api\Checkout;

use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class CheckoutFailureTest extends ApiTestCase
{
    public function test_checkout_fails_when_credits_insufficient(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        // 4 tickets × 505 = 2020 > 2000 available credits
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '4'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(422);
        self::assertStringContainsStringIgnoringCase('credit', $data['message'] ?? '');
    }

    public function test_checkout_fails_when_reservation_expired(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        // Manually insert an expired reservation
        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('-1 minute'));
        $this->em->persist($reservation);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        // Expired reservation treated as empty cart → 400
        $this->assertJsonStatus(400);
    }

    public function test_checkout_fails_when_cart_is_empty(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(400);
    }

    public function test_checkout_fails_for_cancelled_event(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        // Create a pending reservation, then cancel the event
        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);

        $event = $tier->getEvent();
        $event->setStatus('cancelled');
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(409);
    }

    public function test_unauthenticated_checkout_returns_401(): void
    {
        $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => 'test-key']);
        $this->assertJsonStatus(401);
    }
}
