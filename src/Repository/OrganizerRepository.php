<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Organizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organizer>
 */
class OrganizerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organizer::class);
    }

    /**
     * @return array<int, array{organizer: Organizer, eventCount: string}>
     */
    public function findPublicOrganizersWithCounts(): array
    {
        return $this->createQueryBuilder('organizer')
            ->select('organizer', 'user', 'COUNT(DISTINCT event.id) AS eventCount')
            ->join('organizer.user', 'user')
            ->join('organizer.events', 'event')
            ->andWhere('event.status != :cancelled')
            ->andWhere('organizer.approvalStatus = :approved')
            ->andWhere('organizer.deactivatedAt IS NULL')
            ->setParameter('cancelled', Event::STATUS_CANCELLED)
            ->setParameter('approved', Organizer::STATUS_APPROVED)
            ->groupBy('organizer.id', 'user.id')
            ->orderBy('user.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
