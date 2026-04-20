<?php

namespace App\Repository;

use App\Entity\TicketTier;
use App\Entity\User;
use App\Entity\Waitlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Waitlist>
 */
class WaitlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Waitlist::class);
    }

    public function findPendingByUserAndTier(User $user, TicketTier $tier): ?Waitlist
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.ticketTier = :tier')
            ->andWhere('w.status = :status')
            ->setParameter('user', $user)
            ->setParameter('tier', $tier)
            ->setParameter('status', Waitlist::STATUS_PENDING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Waitlist[] */
    public function findPendingByTierOrderedByJoinDate(TicketTier $tier): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.ticketTier = :tier')
            ->andWhere('w.status = :status')
            ->setParameter('tier', $tier)
            ->setParameter('status', Waitlist::STATUS_PENDING)
            ->orderBy('w.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Waitlist[] */
    public function findByUserOrderedByDate(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->join('w.event', 'event')
            ->join('w.ticketTier', 'tier')
            ->addSelect('event', 'tier')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('w.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
