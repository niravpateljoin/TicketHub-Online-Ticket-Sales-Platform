<?php

namespace App\Tests\Api\Messaging;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Message\Notification\BookingConfirmedMessage;
use App\Message\Ticket\GenerateETicketMessage;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

final class CheckoutMessagesTest extends ApiTestCase
{
    public function test_checkout_dispatches_generate_eticket_message(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.ticket');
        $envelopes = $transport->get();

        self::assertNotEmpty($envelopes, 'GenerateETicket message should be dispatched');
        self::assertInstanceOf(GenerateETicketMessage::class, $envelopes[0]->getMessage());
    }

    public function test_checkout_dispatches_booking_confirmed_notification(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);
        $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);
        $this->assertJsonStatus(201);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.notification');
        $envelopes = $transport->get();

        $messages = array_map(static fn ($e): object => $e->getMessage(), $envelopes);
        $confirmMessages = array_filter($messages, static fn (object $m): bool => $m instanceof BookingConfirmedMessage);
        self::assertNotEmpty($confirmMessages, 'BookingConfirmed notification should be dispatched');
    }

    public function test_event_cancellation_dispatches_event_cancelled_message(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.notification');
        $envelopes = $transport->get();

        self::assertNotEmpty($envelopes, 'EventCancelled message should be dispatched');
    }

    public function test_event_cancellation_dispatches_refund_message_per_user(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        // Create a confirmed booking so a refund message will be dispatched
        $booking = new Booking();
        $booking
            ->setUser($user)
            ->setEvent($event)
            ->setTotalCredits(505)
            ->setStatus(Booking::STATUS_CONFIRMED)
            ->setIdempotencyKey(bin2hex(random_bytes(8)));
        $this->em->persist($booking);
        $this->em->flush();

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        /** @var InMemoryTransport $paymentTransport */
        $paymentTransport = static::getContainer()->get('messenger.transport.payment');
        $envelopes = $paymentTransport->get();

        self::assertNotEmpty($envelopes, 'RefundIssued message should be dispatched per booking');
    }
}
