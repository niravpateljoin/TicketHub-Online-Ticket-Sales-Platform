<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Entity\Booking;
use App\Entity\BookingItem;
use App\Entity\Event;
use App\Entity\User;
use App\Service\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheService $cache,
    ) {}

    #[Route('/api/admin/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $stats = $this->cache->getAdminStats(function (): array {
            $totalUsers = (int) $this->em->createQueryBuilder()
                ->select('COUNT(user.id)')
                ->from(User::class, 'user')
                ->andWhere('user.role = :role')
                ->setParameter('role', 'ROLE_USER')
                ->getQuery()
                ->getSingleScalarResult();

            $totalEvents = (int) $this->em->createQueryBuilder()
                ->select('COUNT(event.id)')
                ->from(Event::class, 'event')
                ->getQuery()
                ->getSingleScalarResult();

            $totalTicketsSold = (int) $this->em->createQueryBuilder()
                ->select('COALESCE(SUM(item.quantity), 0)')
                ->from(BookingItem::class, 'item')
                ->join('item.booking', 'booking')
                ->andWhere('booking.status = :status')
                ->setParameter('status', Booking::STATUS_CONFIRMED)
                ->getQuery()
                ->getSingleScalarResult();

            $confirmedBookings = (int) $this->em->createQueryBuilder()
                ->select('COUNT(booking.id)')
                ->from(Booking::class, 'booking')
                ->andWhere('booking.status = :status')
                ->setParameter('status', Booking::STATUS_CONFIRMED)
                ->getQuery()
                ->getSingleScalarResult();

            $feeRows = $this->em->createQueryBuilder()
                ->select('item.unitPrice AS unitPrice, tier.basePrice AS basePrice, item.quantity AS quantity')
                ->from(BookingItem::class, 'item')
                ->join('item.ticketTier', 'tier')
                ->join('item.booking', 'booking')
                ->andWhere('booking.status = :status')
                ->setParameter('status', Booking::STATUS_CONFIRMED)
                ->getQuery()
                ->getArrayResult();

            $totalSystemRevenue = 0;
            foreach ($feeRows as $row) {
                $totalSystemRevenue += ((int) $row['unitPrice'] - (int) $row['basePrice']) * (int) $row['quantity'];
            }

            return [
                'totalUsers'        => $totalUsers,
                'totalEvents'       => $totalEvents,
                'totalTicketsSold'  => $totalTicketsSold,
                'totalSystemRevenue'=> $totalSystemRevenue,
                'registeredUsers'   => $totalUsers,
                'paidOrders'        => $confirmedBookings,
                'revenue'           => $totalSystemRevenue,
                'totalSales'        => $totalSystemRevenue,
            ];
        });

        return $this->success($stats);
    }
}
