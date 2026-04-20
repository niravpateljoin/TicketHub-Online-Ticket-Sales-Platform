<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Event;
use App\Entity\Organizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return array<int, array{category: Category, eventCount: string}>
     */
    public function findPublicCategoriesWithCounts(): array
    {
        return $this->createQueryBuilder('category')
            ->select('category', 'COUNT(DISTINCT event.id) AS eventCount')
            ->join('category.events', 'event')
            ->join('event.organizer', 'organizer')
            ->andWhere('event.status != :cancelled')
            ->andWhere('organizer.approvalStatus = :approved')
            ->andWhere('organizer.deactivatedAt IS NULL')
            ->setParameter('cancelled', Event::STATUS_CANCELLED)
            ->setParameter('approved', Organizer::STATUS_APPROVED)
            ->groupBy('category.id')
            ->orderBy('category.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
