<?php

namespace App\Tests\Fixtures;

use App\Entity\Category;
use App\Entity\Event;
use App\Entity\Organizer;
use App\Entity\TicketTier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TestFixtures
{
    public const ADMIN_EMAIL = 'admin@test.local';
    public const ORGANIZER_APPROVED_EMAIL = 'organizer.approved@test.local';
    public const ORGANIZER_PENDING_EMAIL = 'organizer.pending@test.local';
    public const USER_EMAIL = 'user1@test.local';
    public const USER2_EMAIL = 'user2@test.local';
    public const USER3_EMAIL = 'user3@test.local';

    // Tier name constants — use in tests to look up tiers by name
    public const TIER_GENERAL = 'General';
    public const TIER_LAST_SEAT = 'Last Seat';
    public const TIER_FLASH_UPCOMING = 'Flash Upcoming';
    public const TIER_FLASH_CLOSED = 'Flash Closed';
    public const TIER_SOLD_OUT = 'Flash Sold Out';

    // Base prices
    public const TIER_GENERAL_BASE_PRICE = 500;
    public const TIER_LAST_SEAT_BASE_PRICE = 200;

    public static function seed(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): void
    {
        $concert = (new Category())->setName('Concert');
        $conference = (new Category())->setName('Conference');
        $festival = (new Category())->setName('Festival');
        $em->persist($concert);
        $em->persist($conference);
        $em->persist($festival);

        $admin = new User();
        $admin
            ->setEmail(self::ADMIN_EMAIL)
            ->setName('Admin')
            ->setRole('ROLE_ADMIN')
            ->setPassword($hasher->hashPassword($admin, 'password123'))
            ->setCreditBalance(0)
            ->setIsVerified(true)
            ->setVerifiedAt(new \DateTime());
        $em->persist($admin);

        $approvedOrganizerUser = new User();
        $approvedOrganizerUser
            ->setEmail(self::ORGANIZER_APPROVED_EMAIL)
            ->setName('Approved Organizer')
            ->setRole('ROLE_ORGANIZER')
            ->setPassword($hasher->hashPassword($approvedOrganizerUser, 'password123'))
            ->setIsVerified(true)
            ->setVerifiedAt(new \DateTime());
        $em->persist($approvedOrganizerUser);

        $approvedOrganizer = new Organizer();
        $approvedOrganizer
            ->setUser($approvedOrganizerUser)
            ->setApprovalStatus(Organizer::STATUS_APPROVED)
            ->setApprovedAt(new \DateTime('-1 day'));
        $em->persist($approvedOrganizer);

        $pendingOrganizerUser = new User();
        $pendingOrganizerUser
            ->setEmail(self::ORGANIZER_PENDING_EMAIL)
            ->setName('Pending Organizer')
            ->setRole('ROLE_ORGANIZER')
            ->setPassword($hasher->hashPassword($pendingOrganizerUser, 'password123'))
            ->setIsVerified(true)
            ->setVerifiedAt(new \DateTime());
        $em->persist($pendingOrganizerUser);

        $pendingOrganizer = new Organizer();
        $pendingOrganizer
            ->setUser($pendingOrganizerUser)
            ->setApprovalStatus(Organizer::STATUS_PENDING);
        $em->persist($pendingOrganizer);

        $user1 = new User();
        $user1
            ->setEmail(self::USER_EMAIL)
            ->setName('Test User One')
            ->setRole('ROLE_USER')
            ->setPassword($hasher->hashPassword($user1, 'password123'))
            ->setCreditBalance(2000)
            ->setIsVerified(true)
            ->setVerifiedAt(new \DateTime());
        $em->persist($user1);

        $user2 = new User();
        $user2
            ->setEmail(self::USER2_EMAIL)
            ->setName('Test User Two')
            ->setRole('ROLE_USER')
            ->setPassword($hasher->hashPassword($user2, 'password123'))
            ->setCreditBalance(2000)
            ->setIsVerified(true)
            ->setVerifiedAt(new \DateTime());
        $em->persist($user2);

        $user3 = new User();
        $user3
            ->setEmail(self::USER3_EMAIL)
            ->setName('Test User Three')
            ->setRole('ROLE_USER')
            ->setPassword($hasher->hashPassword($user3, 'password123'))
            ->setCreditBalance(2000)
            ->setIsVerified(true)
            ->setVerifiedAt(new \DateTime());
        $em->persist($user3);

        $rockNight = (new Event())
            ->setOrganizer($approvedOrganizer)
            ->setCategory($concert)
            ->setName('Rock Night Test')
            ->setSlug('rock-night-test')
            ->setDescription('Public concert event')
            ->setDateTime(new \DateTime('+7 days'))
            ->setVenueName('City Arena')
            ->setVenueAddress('Main street')
            ->setIsOnline(false)
            ->setStatus(Event::STATUS_ACTIVE);
        $em->persist($rockNight);

        $phpSummit = (new Event())
            ->setOrganizer($approvedOrganizer)
            ->setCategory($conference)
            ->setName('PHP Summit Test')
            ->setSlug('php-summit-test')
            ->setDescription('Developer conference')
            ->setDateTime(new \DateTime('+10 days'))
            ->setVenueName('Convention Hall')
            ->setVenueAddress('Tech road')
            ->setIsOnline(false)
            ->setStatus(Event::STATUS_ACTIVE);
        $em->persist($phpSummit);

        $cancelledEvent = (new Event())
            ->setOrganizer($approvedOrganizer)
            ->setCategory($festival)
            ->setName('Cancelled Test Event')
            ->setSlug('cancelled-test-event')
            ->setDescription('This event is cancelled')
            ->setDateTime(new \DateTime('+14 days'))
            ->setVenueName('Park')
            ->setVenueAddress('Park street')
            ->setIsOnline(false)
            ->setStatus(Event::STATUS_CANCELLED);
        $em->persist($cancelledEvent);

        $rockTier = (new TicketTier())
            ->setEvent($rockNight)
            ->setName(self::TIER_GENERAL)
            ->setBasePrice(self::TIER_GENERAL_BASE_PRICE)
            ->setTotalSeats(10)
            ->setSaleStartsAt(new \DateTime('-1 day'))
            ->setSaleEndsAt(new \DateTime('+30 days'));
        $em->persist($rockTier);

        $lastSeatTier = (new TicketTier())
            ->setEvent($rockNight)
            ->setName(self::TIER_LAST_SEAT)
            ->setBasePrice(self::TIER_LAST_SEAT_BASE_PRICE)
            ->setTotalSeats(1)
            ->setSaleStartsAt(new \DateTime('-1 day'))
            ->setSaleEndsAt(new \DateTime('+30 days'));
        $em->persist($lastSeatTier);

        $flashUpcomingTier = (new TicketTier())
            ->setEvent($rockNight)
            ->setName(self::TIER_FLASH_UPCOMING)
            ->setBasePrice(300)
            ->setTotalSeats(50)
            ->setSaleStartsAt(new \DateTime('+5 days'))
            ->setSaleEndsAt(new \DateTime('+10 days'));
        $em->persist($flashUpcomingTier);

        $flashClosedTier = (new TicketTier())
            ->setEvent($rockNight)
            ->setName(self::TIER_FLASH_CLOSED)
            ->setBasePrice(300)
            ->setTotalSeats(50)
            ->setSaleStartsAt(new \DateTime('-10 days'))
            ->setSaleEndsAt(new \DateTime('-1 day'));
        $em->persist($flashClosedTier);

        $soldOutTier = (new TicketTier())
            ->setEvent($phpSummit)
            ->setName('Flash Sold Out')
            ->setBasePrice(600)
            ->setTotalSeats(1)
            ->setSoldCount(1)
            ->setSaleStartsAt(new \DateTime('-1 day'))
            ->setSaleEndsAt(new \DateTime('+30 days'));
        $em->persist($soldOutTier);

        $cancelledTier = (new TicketTier())
            ->setEvent($cancelledEvent)
            ->setName('Cancelled Tier')
            ->setBasePrice(300)
            ->setTotalSeats(10);
        $em->persist($cancelledTier);

        $em->flush();
    }
}

