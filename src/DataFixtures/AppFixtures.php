<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\BookingItem;
use App\Entity\Category;
use App\Entity\Event;
use App\Entity\Organizer;
use App\Entity\TicketTier;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── Categories ────────────────────────────────────────────────────────
        $categoryNames = ['Concert', 'Sports', 'Theater', 'Conference', 'Festival', 'Online', 'Comedy', 'Food & Drink'];
        $categories = [];
        foreach ($categoryNames as $name) {
            $cat = (new Category())->setName($name);
            $manager->persist($cat);
            $categories[$name] = $cat;
        }

        // ── Admin ─────────────────────────────────────────────────────────────
        $admin = new User();
        $admin->setEmail('admin@gmail.com')
              ->setName('Platform Admin')
              ->setPassword($this->hasher->hashPassword($admin, 'admin123'))
              ->setRole('ROLE_ADMIN')
              ->setIsVerified(true)
              ->setVerifiedAt(new \DateTime())
              ->setCreditBalance(0);
        $manager->persist($admin);

        // ── Organizers ────────────────────────────────────────────────────────
        $orgData = [
            ['email' => 'or1@gmail.com', 'name' => 'SoundWave Events',     'days' => -60],
            ['email' => 'or2@gmail.com', 'name' => 'SportsMania Pvt Ltd',  'days' => -45],
            ['email' => 'or3@gmail.com', 'name' => 'TechVision Summits',   'days' => -30],
            ['email' => 'or4@gmail.com', 'name' => 'ArtStage Productions', 'days' => -20],
            ['email' => 'or5@gmail.com', 'name' => 'FoodFest India',       'days' => -10],
            ['email' => 'or6@gmail.com', 'name' => 'ComedyCircuit',        'days' => -5],
        ];

        $organizers = [];
        foreach ($orgData as $i => $od) {
            $u = new User();
            $u->setEmail($od['email'])
              ->setName($od['name'])
              ->setPassword($this->hasher->hashPassword($u, 'or123'))
              ->setRole('ROLE_ORGANIZER')
              ->setIsVerified(true)
              ->setVerifiedAt(new \DateTime($od['days'] . ' days'));
            $manager->persist($u);

            $org = new Organizer();
            $org->setUser($u)
                ->setApprovalStatus(Organizer::STATUS_APPROVED)
                ->setApprovedAt(new \DateTime($od['days'] . ' days'));
            $manager->persist($org);
            $organizers[] = $org;
        }

        // Pending organizer (cannot post events)
        $pendingUser = new User();
        $pendingUser->setEmail('pending@platform.com')
                    ->setName('NewComer Events')
                    ->setPassword($this->hasher->hashPassword($pendingUser, 'organizer123'))
                    ->setRole('ROLE_ORGANIZER')
                    ->setIsVerified(true)
                    ->setVerifiedAt(new \DateTime());
        $manager->persist($pendingUser);

        $pendingOrg = new Organizer();
        $pendingOrg->setUser($pendingUser)->setApprovalStatus(Organizer::STATUS_PENDING);
        $manager->persist($pendingOrg);

        // ── Regular Users ─────────────────────────────────────────────────────
        $users = [];
        $userNames = ['Arjun Sharma', 'Priya Patel', 'Rahul Singh', 'Sneha Reddy', 'Karan Mehta',
                      'Divya Nair', 'Amit Kumar', 'Pooja Iyer', 'Rohan Gupta', 'Meera Joshi'];
        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setEmail('user' . ($i + 1) . '@platform.com')
                 ->setName($userNames[$i])
                 ->setPassword($this->hasher->hashPassword($user, 'user123'))
                 ->setRole('ROLE_USER')
                 ->setCreditBalance(3000 + $i * 200);
            $manager->persist($user);
            $users[] = $user;
        }

        // ── Events ────────────────────────────────────────────────────────────
        // Helper: create event + tiers in one call
        // organizer, category, name, slug, desc, dateTime, venue, address, isOnline, status, tiers[]
        $eventsData = [
            // ── SoundWave Events (organizers[0]) ─ Concerts & Festivals ──────
            [
                'org'      => $organizers[0],
                'cat'      => 'Concert',
                'name'     => 'Rock Night 2026',
                'slug'     => 'rock-night-2026',
                'desc'     => 'An epic night of rock music featuring top bands from across the country. Headlined by Motherjane and Thermal & A Quarter.',
                'date'     => '+30 days',
                'venue'    => 'Jawaharlal Nehru Stadium',
                'address'  => 'Lodhi Road, New Delhi 110003',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['General Admission', 600,  300],
                    ['VIP Pit',           1800,  80],
                    ['Premium Lounge',    3500,  30],
                ],
            ],
            [
                'org'      => $organizers[0],
                'cat'      => 'Concert',
                'name'     => 'Bollywood Beats Night',
                'slug'     => 'bollywood-beats-night',
                'desc'     => 'Dance the night away to the best Bollywood hits performed live by chart-topping artists.',
                'date'     => '+50 days',
                'venue'    => 'NSCI Dome',
                'address'  => 'Dr. Annie Besant Road, Worli, Mumbai 400018',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Floor Standing', 800,  400],
                    ['Seated',         1200, 200],
                    ['VIP Table',      4000,  20],
                ],
            ],
            [
                'org'      => $organizers[0],
                'cat'      => 'Festival',
                'name'     => 'Summer Beats Festival',
                'slug'     => 'summer-beats-festival',
                'desc'     => 'Three days of music, art, and culture in the open air. 50+ artists across 4 stages.',
                'date'     => '+75 days',
                'venue'    => 'Mahalaxmi Race Course',
                'address'  => 'Mahalaxmi, Mumbai 400034',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Day Pass — Day 1',  500, 1000],
                    ['Day Pass — Day 2',  500, 1000],
                    ['Full Weekend Pass', 1200, 400],
                    ['Camping + Festival', 2000, 100],
                ],
            ],
            [
                'org'      => $organizers[0],
                'cat'      => 'Concert',
                'name'     => 'Jazz Under the Stars',
                'slug'     => 'jazz-under-the-stars',
                'desc'     => 'An intimate evening of smooth jazz and blues with world-class performers.',
                'date'     => '-10 days',
                'venue'    => 'Amphitheatre, Cubbon Park',
                'address'  => 'Kasturba Road, Bengaluru 560001',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['General', 400, 200],
                    ['Reserved Seating', 800, 60],
                ],
            ],

            // ── SportsMania (organizers[1]) ─ Sports ──────────────────────────
            [
                'org'      => $organizers[1],
                'cat'      => 'Sports',
                'name'     => 'Pro Kabaddi League — Grand Finale',
                'slug'     => 'pro-kabaddi-grand-finale',
                'desc'     => 'The most-awaited kabaddi finale of the year. Watch the top two teams battle for the trophy.',
                'date'     => '+20 days',
                'venue'    => 'Sardar Vallabhbhai Patel Indoor Stadium',
                'address'  => 'Worli, Mumbai 400025',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['East Stand',  300, 500],
                    ['West Stand',  300, 500],
                    ['Courtside',  1200,  80],
                    ['Corporate Box', 5000, 10],
                ],
            ],
            [
                'org'      => $organizers[1],
                'cat'      => 'Sports',
                'name'     => 'Delhi Marathon 2026',
                'slug'     => 'delhi-marathon-2026',
                'desc'     => 'The annual Delhi Marathon — join 15,000 runners for the full, half, and 10K categories.',
                'date'     => '+55 days',
                'venue'    => 'Jawaharlal Nehru Stadium',
                'address'  => 'Bhishma Pitamah Marg, New Delhi 110003',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['10K Run',     400, 5000],
                    ['Half Marathon', 700, 5000],
                    ['Full Marathon', 1200, 3000],
                    ['Elite Category', 2000,  200],
                ],
            ],
            [
                'org'      => $organizers[1],
                'cat'      => 'Sports',
                'name'     => 'IPL Watch Party — Mumbai vs Delhi',
                'slug'     => 'ipl-watch-party-mum-vs-del',
                'desc'     => 'Join hundreds of fans at the official IPL watch party! Big screens, food, and cricket madness.',
                'date'     => '+14 days',
                'venue'    => 'Hard Rock Cafe',
                'address'  => 'Phoenix Palladium, Lower Parel, Mumbai',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['General Entry', 250, 300],
                    ['VIP Seating',   800,  50],
                ],
            ],

            // ── TechVision Summits (organizers[2]) ─ Conferences & Online ────
            [
                'org'      => $organizers[2],
                'cat'      => 'Conference',
                'name'     => 'PHP Summit 2026',
                'slug'     => 'php-summit-2026',
                'desc'     => 'The premier PHP developer conference with workshops, keynotes, and hands-on labs by industry leaders.',
                'date'     => '+40 days',
                'venue'    => 'Bangalore International Exhibition Centre',
                'address'  => '10th Mile, Tumkur Road, Bengaluru 562123',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Standard Pass', 1500, 400],
                    ['Workshop Pass', 2500, 120],
                    ['All-Access',    4000,  50],
                ],
            ],
            [
                'org'      => $organizers[2],
                'cat'      => 'Conference',
                'name'     => 'AI & Machine Learning India 2026',
                'slug'     => 'ai-ml-india-2026',
                'desc'     => 'India\'s largest AI conference. Two days of talks, demos, and networking with AI practitioners.',
                'date'     => '+65 days',
                'venue'    => 'Hyderabad International Convention Centre',
                'address'  => 'Novotel & HICC Complex, Hyderabad 500081',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Standard',   2000, 600],
                    ['Premium',    4000, 100],
                    ['Startup Pass', 800, 200],
                ],
            ],
            [
                'org'      => $organizers[2],
                'cat'      => 'Online',
                'name'     => 'Full Stack Web Dev Bootcamp',
                'slug'     => 'fullstack-bootcamp-2026',
                'desc'     => 'A 4-week live online bootcamp covering React, Node.js, PostgreSQL, and deployment. Certificate included.',
                'date'     => '+10 days',
                'venue'    => 'Online (Zoom)',
                'address'  => null,
                'online'   => true,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Audit (No Certificate)', 199,  500],
                    ['Full Access',            999,  300],
                    ['Mentored Track',        2499,   50],
                ],
            ],
            [
                'org'      => $organizers[2],
                'cat'      => 'Online',
                'name'     => 'Cloud Architecture Masterclass',
                'slug'     => 'cloud-architecture-masterclass',
                'desc'     => 'One-day live workshop on AWS, Azure, and GCP. Learn to design scalable, cost-efficient cloud systems.',
                'date'     => '+7 days',
                'venue'    => 'Online (Google Meet)',
                'address'  => null,
                'online'   => true,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Individual',     599,  200],
                    ['Team (5 seats)', 2499,   40],
                ],
            ],

            // ── ArtStage Productions (organizers[3]) ─ Theater & Comedy ──────
            [
                'org'      => $organizers[3],
                'cat'      => 'Theater',
                'name'     => 'Mughal-E-Azam: The Musical',
                'slug'     => 'mughal-e-azam-musical',
                'desc'     => 'The iconic story of Anarkali and Salim reimagined as a spectacular musical. Lavish sets, live orchestra.',
                'date'     => '+35 days',
                'venue'    => 'National Centre for the Performing Arts',
                'address'  => 'Nariman Point, Mumbai 400021',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Rear Stalls',   800, 120],
                    ['Front Stalls', 1500,  80],
                    ['Dress Circle', 2200,  60],
                    ['Royal Box',    5000,  10],
                ],
            ],
            [
                'org'      => $organizers[3],
                'cat'      => 'Theater',
                'name'     => 'Waiting for Godot — Contemporary Indian Adaptation',
                'slug'     => 'waiting-for-godot-india',
                'desc'     => 'A critically acclaimed modern adaptation of Beckett\'s masterpiece set in rural Rajasthan.',
                'date'     => '+25 days',
                'venue'    => 'Prithvi Theatre',
                'address'  => 'Juhu Church Road, Juhu, Mumbai 400049',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Standard', 400, 150],
                    ['Premium',  700,  50],
                ],
            ],

            // ── FoodFest India (organizers[4]) ─ Food & Drink ────────────────
            [
                'org'      => $organizers[4],
                'cat'      => 'Food & Drink',
                'name'     => 'Mumbai Street Food Festival',
                'slug'     => 'mumbai-street-food-festival',
                'desc'     => '200+ stalls, live cooking demos, chef competitions, and unlimited tastings across two days.',
                'date'     => '+45 days',
                'venue'    => 'BKC Ground',
                'address'  => 'Bandra Kurla Complex, Mumbai 400051',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Day Pass',          200, 2000],
                    ['Weekend Pass',      350,  800],
                    ['VIP Food Passport', 800,  100],
                ],
            ],
            [
                'org'      => $organizers[4],
                'cat'      => 'Food & Drink',
                'name'     => 'Craft Beer & BBQ Carnival',
                'slug'     => 'craft-beer-bbq-carnival',
                'desc'     => 'India\'s biggest craft beer gathering. 40+ breweries, live music, and a dedicated BBQ competition.',
                'date'     => '+28 days',
                'venue'    => 'Nesco Center',
                'address'  => 'Western Express Highway, Goregaon East, Mumbai 400063',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Standard Entry (Includes 5 tokens)', 500,  600],
                    ['VIP (Includes 15 tokens + lounge)',  1200,  80],
                ],
            ],
            [
                'org'      => $organizers[4],
                'cat'      => 'Food & Drink',
                'name'     => 'South Indian Food Fiesta',
                'slug'     => 'south-indian-food-fiesta',
                'desc'     => 'A celebration of dosas, idlis, biryani, and coastal seafood from Tamil Nadu, Kerala, Karnataka & Andhra.',
                'date'     => '-5 days',
                'venue'    => 'Jawaharlal Nehru Indoor Stadium',
                'address'  => 'Indraprastha Estate, New Delhi 110002',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Entry Pass', 150, 3000],
                    ['Tasting Pass (includes 10 food tokens)', 400, 500],
                ],
            ],

            // ── ComedyCircuit (organizers[5]) ─ Comedy ───────────────────────
            [
                'org'      => $organizers[5],
                'cat'      => 'Comedy',
                'name'     => 'The Big Comedy Night',
                'slug'     => 'big-comedy-night-2026',
                'desc'     => 'Five of India\'s top stand-up comedians in one unforgettable night. Expect to laugh non-stop for 3 hours.',
                'date'     => '+18 days',
                'venue'    => 'St. Andrews Auditorium',
                'address'  => 'Hill Road, Bandra West, Mumbai 400050',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Standard',  600, 250],
                    ['Premium',  1000,  80],
                    ['Front Row', 1500,  30],
                ],
            ],
            [
                'org'      => $organizers[5],
                'cat'      => 'Comedy',
                'name'     => 'Open Mic: Emerging Comedians Showcase',
                'slug'     => 'open-mic-emerging-comedians',
                'desc'     => 'Discover the next big names in Indian comedy. 15+ fresh acts in a cozy 100-seat venue.',
                'date'     => '+8 days',
                'venue'    => 'The Comedy Store',
                'address'  => 'Lower Parel, Mumbai 400013',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['General', 200, 100],
                ],
            ],
            [
                'org'      => $organizers[5],
                'cat'      => 'Comedy',
                'name'     => 'Comedy Gala — Bangalore Edition',
                'slug'     => 'comedy-gala-bangalore',
                'desc'     => 'A star-studded comedy gala featuring headliners and special guest appearances.',
                'date'     => '+60 days',
                'venue'    => 'Chowdiah Memorial Hall',
                'address'  => 'Vyalikaval, Bengaluru 560003',
                'online'   => false,
                'status'   => Event::STATUS_ACTIVE,
                'tiers'    => [
                    ['Balcony',   500, 200],
                    ['Ground',    800, 150],
                    ['Courtside', 1400,  40],
                ],
            ],

            // ── Sold-out event example ────────────────────────────────────────
            [
                'org'      => $organizers[0],
                'cat'      => 'Concert',
                'name'     => 'AR Rahman Live — Sold Out',
                'slug'     => 'ar-rahman-live-sold-out',
                'desc'     => 'The Mozart of Madras performs his greatest hits live. All tickets sold.',
                'date'     => '+22 days',
                'venue'    => 'DY Patil Stadium',
                'address'  => 'Sector 7, Nerul, Navi Mumbai 400614',
                'online'   => false,
                'status'   => Event::STATUS_SOLD_OUT,
                'tiers'    => [
                    ['Floor', 2000, 5000],
                    ['Upper Tier', 1000, 3000],
                ],
            ],

            // ── Postponed event example ───────────────────────────────────────
            [
                'org'      => $organizers[1],
                'cat'      => 'Sports',
                'name'     => 'Champions Trophy Watch Party [Postponed]',
                'slug'     => 'champions-trophy-watch-postponed',
                'desc'     => 'Event postponed due to venue maintenance. New date to be announced.',
                'date'     => '+100 days',
                'venue'    => 'Sports Bar, Phoenix',
                'address'  => 'Lower Parel, Mumbai',
                'online'   => false,
                'status'   => Event::STATUS_POSTPONED,
                'tiers'    => [
                    ['General', 300, 200],
                ],
            ],
        ];

        $allTiers  = [];
        $allEvents = [];

        foreach ($eventsData as $ed) {
            $event = new Event();
            $event->setOrganizer($ed['org'])
                  ->setCategory($categories[$ed['cat']])
                  ->setName($ed['name'])
                  ->setSlug($ed['slug'])
                  ->setDescription($ed['desc'])
                  ->setDateTime(new \DateTime($ed['date']))
                  ->setVenueName($ed['venue'])
                  ->setIsOnline($ed['online'])
                  ->setStatus($ed['status']);

            if ($ed['address'] !== null) {
                $event->setVenueAddress($ed['address']);
            }

            $manager->persist($event);
            $allEvents[] = $event;

            $eventTiers = [];
            foreach ($ed['tiers'] as [$tierName, $price, $seats]) {
                $tier = new TicketTier();
                $tier->setEvent($event)
                     ->setName($tierName)
                     ->setBasePrice($price)
                     ->setTotalSeats($seats);
                $manager->persist($tier);
                $eventTiers[] = $tier;
            }
            $allTiers[] = $eventTiers;
        }

        // ── Sample Bookings ───────────────────────────────────────────────────
        // Each booking: [userIndex, eventIndex, tierIndex, qty]
        $bookingDefs = [
            [0, 0,  0, 1],  // Arjun → Rock Night GA
            [0, 0,  1, 2],  // Arjun → Rock Night VIP x2
            [1, 1,  0, 2],  // Priya → Bollywood Floor x2
            [2, 4,  0, 4],  // Rahul → Kabaddi East Stand x4
            [3, 7,  0, 1],  // Sneha → PHP Summit Standard
            [4, 9,  1, 1],  // Karan → AI/ML Premium
            [5, 10, 1, 1],  // Divya → Bootcamp Full Access
            [6, 12, 0, 2],  // Amit → Mughal-E-Azam Rear Stalls x2
            [7, 13, 0, 3],  // Pooja → Godot Standard x3
            [8, 14, 2, 1],  // Rohan → Street Food VIP
            [9, 16, 1, 2],  // Meera → Comedy Night Premium x2
            [0, 17, 0, 1],  // Arjun → Open Mic
            [1, 5,  0, 1],  // Priya → Delhi Marathon 10K
            [2, 8,  2, 1],  // Rahul → AI/ML Startup Pass
            [3, 15, 0, 2],  // Sneha → Beer Carnival x2
        ];

        foreach ($bookingDefs as [$uIdx, $eIdx, $tIdx, $qty]) {
            if (!isset($users[$uIdx], $allTiers[$eIdx], $allTiers[$eIdx][$tIdx])) {
                continue;
            }

            $user  = $users[$uIdx];
            $event = $allEvents[$eIdx];
            $tier  = $allTiers[$eIdx][$tIdx];

            $unitPrice   = (int) round($tier->getBasePrice() * 1.01);
            $totalCost   = $unitPrice * $qty;

            if ($user->getCreditBalance() < $totalCost) {
                continue;
            }

            $booking = new Booking();
            $booking->setUser($user)
                    ->setEvent($event)
                    ->setTotalCredits($totalCost)
                    ->setStatus(Booking::STATUS_CONFIRMED)
                    ->setIdempotencyKey(bin2hex(random_bytes(16)));
            $manager->persist($booking);

            $item = new BookingItem();
            $item->setBooking($booking)
                 ->setTicketTier($tier)
                 ->setQuantity($qty)
                 ->setUnitPrice($unitPrice);
            $manager->persist($item);

            $tier->setSoldCount($tier->getSoldCount() + $qty);

            $user->setCreditBalance($user->getCreditBalance() - $totalCost);

            $tx = new Transaction();
            $tx->setUser($user)
               ->setAmount($totalCost)
               ->setType(Transaction::TYPE_DEBIT)
               ->setReference(sprintf('Booking: %s — %s', $event->getName(), $tier->getName()));
            $manager->persist($tx);
        }

        $manager->flush();
    }
}
