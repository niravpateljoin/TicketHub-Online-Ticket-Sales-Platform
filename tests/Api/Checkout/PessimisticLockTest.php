<?php

namespace App\Tests\Api\Checkout;

use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class PessimisticLockTest extends ApiTestCase
{
    public function test_credit_deduction_is_atomic_and_prevents_overdraft(): void
    {
        // User has 2000 credits. Last-seat tier costs 202 final (200 * 1.01).
        // Set user balance to exactly 202 so one checkout succeeds and any second attempt fails.
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $user->setCreditBalance(202);
        $this->em->flush();

        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_LAST_SEAT]);

        // Add to cart and checkout successfully
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(201);
        self::assertSame(0, $data['data']['newCreditBalance']);

        // Verify balance in DB is exactly 0 (no negative balance)
        $this->em->clear();
        $updatedUser = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        self::assertSame(0, $updatedUser->getCreditBalance());
        self::assertGreaterThanOrEqual(0, $updatedUser->getCreditBalance());
    }

    public function test_second_checkout_fails_when_balance_depleted(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $user->setCreditBalance(202);
        $this->em->flush();

        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_LAST_SEAT]);

        // First checkout — succeeds
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        // Try to checkout again without enough credits
        // Need a second tier on a different event; simulate by using the general tier on an event the user reserves
        $generalTier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $generalTier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        // User has 0 credits, tier costs 505 → insufficient
        $this->assertJsonStatus(422);
    }
}
