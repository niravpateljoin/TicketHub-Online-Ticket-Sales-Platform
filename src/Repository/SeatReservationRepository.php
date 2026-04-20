<?php

namespace App\Repository;

use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeatReservation>
 */
class SeatReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeatReservation::class);
    }

    /**
     * @return SeatReservation[]
     */
    public function findActiveCartReservationsForUser(User $user): array
    {
        return $this->createQueryBuilder('reservation')
            ->join('reservation.ticketTier', 'tier')
            ->join('tier.event', 'event')
            ->addSelect('tier', 'event')
            ->andWhere('reservation.user = :user')
            ->andWhere('reservation.status = :status')
            ->andWhere('reservation.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('status', SeatReservation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('reservation.expiresAt', 'ASC')
            ->addOrderBy('reservation.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SeatReservation[]
     */
    public function findExpiredPendingReservationsForUser(User $user): array
    {
        return $this->createQueryBuilder('reservation')
            ->andWhere('reservation.user = :user')
            ->andWhere('reservation.status = :status')
            ->andWhere('reservation.expiresAt <= :now')
            ->setParameter('user', $user)
            ->setParameter('status', SeatReservation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all pending reservations whose expiresAt has passed (global, across all users).
     * Used by the ExpireSeatReservationsCommand cron job.
     *
     * @return SeatReservation[]
     */
    public function findExpiredPending(): array
    {
        return $this->createQueryBuilder('reservation')
            ->andWhere('reservation.status = :status')
            ->andWhere('reservation.expiresAt <= :now')
            ->setParameter('status', SeatReservation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function sumActiveReservedQuantityForTier(TicketTier $tier, array $excludedReservationIds = []): int
    {
        $qb = $this->createQueryBuilder('reservation')
            ->select('COALESCE(SUM(reservation.quantity), 0)')
            ->andWhere('reservation.ticketTier = :tier')
            ->andWhere('reservation.status = :status')
            ->andWhere('reservation.expiresAt > :now')
            ->setParameter('tier', $tier)
            ->setParameter('status', SeatReservation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable());

        if ($excludedReservationIds !== []) {
            $qb
                ->andWhere($qb->expr()->notIn('reservation.id', ':excludedIds'))
                ->setParameter('excludedIds', $excludedReservationIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
