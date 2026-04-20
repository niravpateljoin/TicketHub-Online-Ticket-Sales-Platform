<?php

namespace App\Tests\Api\Checkout;

use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class FlashSaleWindowTest extends ApiTestCase
{
    public function test_cannot_checkout_before_tier_sale_starts(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_FLASH_UPCOMING]);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        // Force a pending reservation bypassing the add-to-cart window check
        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(409);
    }

    public function test_cannot_checkout_after_tier_sale_ends(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_FLASH_CLOSED]);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);
        $this->em->flush();

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(409);
    }

    public function test_can_checkout_when_sale_is_open(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(201);
    }

    public function test_server_time_used_not_client_time(): void
    {
        // The sale window check happens server-side on every checkout call.
        // Sending a manipulated timestamp in the body must not bypass the check.
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_FLASH_UPCOMING]);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity(1)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);
        $this->em->flush();

        // Include a fake "clientTime" suggesting the sale window is open — server must ignore it
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', [
            'idempotencyKey' => bin2hex(random_bytes(8)),
            'clientTime' => (new \DateTime('+10 days'))->format(\DateTimeInterface::ATOM),
        ], $token);

        // Sale window enforcement is server-side; fake clientTime must not bypass it
        $this->assertJsonStatus(409);
    }
}
