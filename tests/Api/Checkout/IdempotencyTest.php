<?php

namespace App\Tests\Api\Checkout;

use App\Entity\Booking;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class IdempotencyTest extends ApiTestCase
{
    public function test_same_idempotency_key_does_not_double_charge(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $idempotencyKey = bin2hex(random_bytes(8));

        // First checkout
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $res1 = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => $idempotencyKey], $token);
        $this->assertJsonStatus(201);

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $balanceAfterFirst = $user->getCreditBalance();

        // Second checkout with the same key — must not charge again
        $res2 = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => $idempotencyKey], $token);
        // Returns already-processed response (200 with alreadyProcessed flag)
        self::assertSame($res1['data']['bookingId'], $res2['data']['bookingId']);
        self::assertTrue($res2['data']['alreadyProcessed'] ?? false);

        $this->em->clear();
        $userAfter = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        self::assertSame($balanceAfterFirst, $userAfter->getCreditBalance());

        // Only one booking should exist for this key
        $bookings = $this->em->getRepository(Booking::class)->findBy(['idempotencyKey' => $idempotencyKey]);
        self::assertCount(1, $bookings);
    }
}
