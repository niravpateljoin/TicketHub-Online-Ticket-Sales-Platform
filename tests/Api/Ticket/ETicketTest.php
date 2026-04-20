<?php

namespace App\Tests\Api\Ticket;

use App\Entity\Booking;
use App\Entity\BookingItem;
use App\Entity\ETicket;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class ETicketTest extends ApiTestCase
{
    public function test_eticket_created_after_checkout(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        $booking = $this->em->getRepository(Booking::class)->find($data['data']['bookingId']);
        $item = $booking->getBookingItems()->first();
        self::assertNotNull($item->getETicket());
        self::assertNotEmpty($item->getETicket()->getQrToken());
    }

    public function test_ticket_download_returns_202_if_pdf_not_yet_generated(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        $booking = $this->em->getRepository(Booking::class)->find($data['data']['bookingId']);
        $eTicket = $booking->getBookingItems()->first()->getETicket();

        // PDF not generated yet → 202 Accepted
        $this->jsonRequest('GET', '/api/tickets/'.$eTicket->getQrToken().'/download', token: $token);
        $this->assertJsonStatus(202);
    }

    public function test_ticket_download_returns_403_for_non_owner(): void
    {
        // User1 buys a ticket
        $token1 = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token1);
        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token1);
        $this->assertJsonStatus(201);

        $booking = $this->em->getRepository(Booking::class)->find($data['data']['bookingId']);

        // User2 tries to download via booking endpoint
        $token2 = $this->loginAs(TestFixtures::USER2_EMAIL, 'password123');
        $this->jsonRequest('GET', '/api/bookings/'.$booking->getId().'/ticket', token: $token2);
        $this->assertJsonStatus(403);
    }

    public function test_qr_token_is_unique_per_booking_item(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        // First booking
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $data1 = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        // Second booking by user2
        $token2 = $this->loginAs(TestFixtures::USER2_EMAIL, 'password123');
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token2);
        $data2 = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token2);
        $this->assertJsonStatus(201);

        $booking1 = $this->em->getRepository(Booking::class)->find($data1['data']['bookingId']);
        $booking2 = $this->em->getRepository(Booking::class)->find($data2['data']['bookingId']);

        $qr1 = $booking1->getBookingItems()->first()->getETicket()->getQrToken();
        $qr2 = $booking2->getBookingItems()->first()->getETicket()->getQrToken();

        self::assertNotSame($qr1, $qr2);
    }
}
