<?php

namespace App\Tests\Api\Admin;

use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\Transaction;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class AdminEventCancelTest extends ApiTestCase
{
    public function test_admin_can_cancel_any_event_and_refund_all_holders(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        // Set up: create a confirmed booking for user1 on Rock Night
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->createConfirmedBooking($user, $event, $tier, 1, 505);
        $this->em->refresh($user);
        $initialBalance = $user->getCreditBalance();

        $data = $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $token);

        $this->assertJsonStatus(200);
        self::assertSame(1, $data['data']['usersRefunded'] ?? null);
        self::assertSame(505, $data['data']['creditsRefunded'] ?? null);

        $this->em->clear();
        $refundedUser = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        self::assertSame($initialBalance + 505, $refundedUser->getCreditBalance());
    }

    public function test_cancel_event_creates_refund_transaction_per_booking(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestFixtures::USER_EMAIL]);
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_GENERAL]);

        $this->createConfirmedBooking($user, $event, $tier, 1, 505);

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $token);
        $this->assertJsonStatus(200);

        $this->em->clear();
        $transactions = $this->em->getRepository(Transaction::class)->findBy([
            'user' => $user,
            'type' => Transaction::TYPE_REFUND,
        ]);
        self::assertCount(1, $transactions);
        self::assertSame(505, $transactions[0]->getAmount());
    }

    public function test_cancel_already_cancelled_event_is_safe(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');

        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'cancelled-test-event']);

        // Already cancelled — should return 0 refunds with no error
        $data = $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $token);

        $this->assertJsonStatus(200);
        self::assertSame(0, $data['data']['usersRefunded'] ?? -1);
        self::assertSame(0, $data['data']['creditsRefunded'] ?? -1);
    }

    public function test_non_admin_cannot_cancel_event(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $this->jsonRequest('POST', '/api/admin/events/'.$event->getId().'/cancel', token: $token);
        $this->assertJsonStatus(403);
    }

    private function createConfirmedBooking(User $user, Event $event, TicketTier $tier, int $qty, int $totalCredits): Booking
    {
        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity($qty)
            ->setStatus(SeatReservation::STATUS_CONFIRMED)
            ->setExpiresAt(new \DateTime('+10 minutes'));
        $this->em->persist($reservation);

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
