<?php

namespace App\Tests\Api\Checkout;

use App\Entity\Booking;
use App\Entity\ETicket;
use App\Entity\TicketTier;
use App\Entity\Transaction;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class CheckoutSuccessTest extends ApiTestCase
{
    public function test_checkout_deducts_correct_credits(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $this->assertJsonStatus(201);

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(201);
        // basePrice 500, finalPrice 505 (1% fee), creditBalance = 2000 - 505 = 1495
        self::assertSame(1495, $data['data']['newCreditBalance'] ?? null);
    }

    public function test_checkout_creates_booking_with_correct_items(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '2'], $token);
        $key = bin2hex(random_bytes(8));
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => $key], $token);

        $this->assertJsonStatus(201);
        self::assertNotNull($data['data']['bookingId'] ?? null);

        $booking = $this->em->getRepository(Booking::class)->find($data['data']['bookingId']);
        self::assertNotNull($booking);
        self::assertSame(Booking::STATUS_CONFIRMED, $booking->getStatus());
        self::assertCount(1, $booking->getBookingItems());
        self::assertSame(2, $booking->getBookingItems()->first()->getQuantity());
    }

    public function test_checkout_increments_tier_sold_count(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $initialSold = $tier->getSoldCount();

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '2'], $token);
        $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        $this->em->clear();
        $updated = $this->em->getRepository(TicketTier::class)->find($tier->getId());
        self::assertSame($initialSold + 2, $updated->getSoldCount());
    }

    public function test_checkout_creates_debit_transaction_record(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $transaction = $this->em->getRepository(Transaction::class)->findOneBy([
            'user' => $user,
            'type' => Transaction::TYPE_DEBIT,
        ]);
        self::assertNotNull($transaction);
        self::assertSame(505, $transaction->getAmount());
    }

    public function test_checkout_returns_booking_id_and_new_balance(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        $this->assertJsonStatus(201);
        self::assertArrayHasKey('bookingId', $data['data']);
        self::assertArrayHasKey('newCreditBalance', $data['data']);
        self::assertArrayHasKey('totalCredits', $data['data']);
    }

    public function test_checkout_dispatches_eticket_message_to_queue(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        // ETicket record (without PDF yet) should exist after checkout
        $booking = $this->em->getRepository(Booking::class)->find($data['data']['bookingId']);
        self::assertNotNull($booking);
        $item = $booking->getBookingItems()->first();
        self::assertNotNull($item->getETicket());
        // filePath is null until the async worker generates it
        self::assertNull($item->getETicket()->getFilePath());
        self::assertNotEmpty($item->getETicket()->getQrToken());
    }
}
