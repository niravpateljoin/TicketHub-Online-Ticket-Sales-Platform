<?php

namespace App\Tests\Api\Refund;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\Transaction;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class EventCancellationTest extends ApiTestCase
{
    public function test_cancelling_event_refunds_all_confirmed_bookings(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $initialBalance = $user->getCreditBalance();

        $this->createConfirmedBooking($user, $event, 505);

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $updatedUser = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        self::assertSame($initialBalance + 505, $updatedUser->getCreditBalance());
    }

    public function test_refunded_booking_shows_correct_status(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $booking = $this->createConfirmedBooking($user, $event, 505);
        $bookingId = $booking->getId();

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $updatedBooking = $this->em->getRepository(Booking::class)->find($bookingId);
        self::assertSame(Booking::STATUS_REFUNDED, $updatedBooking->getStatus());
    }

    public function test_cancelled_event_cannot_be_purchased(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => 'Cancelled Tier']);

        $data = $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $token);

        $this->assertJsonStatus(409);
    }

    public function test_cancelling_event_twice_does_not_double_refund(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $this->createConfirmedBooking($user, $event, 505);

        // First cancel
        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $balanceAfterFirst = $user->getCreditBalance();

        // Second cancel — should be no-op (event already cancelled)
        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $userAfterSecond = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        self::assertSame($balanceAfterFirst, $userAfterSecond->getCreditBalance());
    }

    public function test_pending_reservations_expired_on_event_cancel(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $userToken = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');

        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);
        $event = $tier->getEvent();

        // Add to cart (creates a pending reservation)
        $this->jsonRequest('POST', '/api/cart', ['tierId' => (string) $tier->getId(), 'quantity' => '1'], $userToken);
        $this->assertJsonStatus(201);

        // Cancel the event
        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $reservations = $this->em->getRepository(SeatReservation::class)->findBy([
            'user' => $user,
            'status' => SeatReservation::STATUS_PENDING,
        ]);
        self::assertCount(0, $reservations);
    }

    public function test_refund_transaction_records_created_per_user(): void
    {
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $user1 = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $user2 = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER2_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $this->createConfirmedBooking($user1, $event, 505);
        $this->createConfirmedBooking($user2, $event, 505);

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $adminToken);
        $this->assertJsonStatus(200);

        $this->em->clear();
        foreach ([$user1->getId(), $user2->getId()] as $userId) {
            $user = $this->em->getRepository(User::class)->find($userId);
            $refund = $this->em->getRepository(Transaction::class)->findOneBy([
                'user' => $user,
                'type' => Transaction::TYPE_REFUND,
            ]);
            self::assertNotNull($refund, "No refund transaction for user {$userId}");
        }
    }

    private function createConfirmedBooking(User $user, Event $event, int $totalCredits): Booking
    {
        $booking = new Booking();
        $booking
            ->setUser($user)
            ->setEvent($event)
            ->setTotalCredits($totalCredits)
            ->setStatus(Booking::STATUS_CONFIRMED)
            ->setIdempotencyKey(bin2hex(random_bytes(8)));
        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }
}
