<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Organizer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findFiltered(array $filters, int $page, int $perPage = 12): array
    {
        $qb = $this->createPublicFilteredQueryBuilder($filters);

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT event.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }

    /**
     * @return Event[]
     */
    public function findUpcoming(int $limit = 6): array
    {
        return $this->createQueryBuilder('event')
            ->join('event.organizer', 'organizer')
            ->addSelect('organizer')
            ->andWhere('event.status = :active')
            ->andWhere('event.dateTime > :now')
            ->andWhere('organizer.approvalStatus = :approved')
            ->andWhere('organizer.deactivatedAt IS NULL')
            ->setParameter('active', Event::STATUS_ACTIVE)
            ->setParameter('approved', Organizer::STATUS_APPROVED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('event.dateTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Event[]
     */
    public function findByOrganizer(Organizer $organizer): array
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.organizer = :organizer')
            ->setParameter('organizer', $organizer)
            ->orderBy('event.dateTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?Event
    {
        return $this->createQueryBuilder('event')
            ->andWhere('event.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function createPublicFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('event')
            ->join('event.category', 'category')
            ->join('event.organizer', 'organizer')
            ->join('organizer.user', 'organizerUser')
            ->leftJoin('event.ticketTiers', 'tier')
            ->addSelect('category', 'organizer', 'organizerUser')
            ->andWhere('event.status != :cancelled')
            ->andWhere('organizer.approvalStatus = :approved')
            ->andWhere('organizer.deactivatedAt IS NULL')
            ->setParameter('cancelled', Event::STATUS_CANCELLED)
            ->setParameter('approved', Organizer::STATUS_APPROVED)
            ->addSelect('CASE WHEN event.dateTime >= CURRENT_TIMESTAMP() THEN 0 ELSE 1 END AS HIDDEN upcomingRank')
            ->orderBy('upcomingRank', 'ASC')
            ->addOrderBy('event.dateTime', 'ASC')
            ->distinct();

        $search = trim((string) ($filters['search'] ?? ($filters['q'] ?? '')));
        if ($search !== '') {
            // Use PostgreSQL full-text search via search_vector generated column
            $conn = $this->getEntityManager()->getConnection();
            $ids  = $conn->executeQuery(
                "SELECT id FROM events WHERE search_vector @@ plainto_tsquery('english', ?)",
                [$search]
            )->fetchFirstColumn();

            if (empty($ids)) {
                // No full-text matches — fall back to LIKE for partial matches
                $qb
                    ->andWhere('LOWER(event.name) LIKE :search OR LOWER(COALESCE(event.description, \'\')) LIKE :searchLike')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%')
                    ->setParameter('searchLike', '%' . mb_strtolower($search) . '%');
            } else {
                $qb->andWhere('event.id IN (:searchIds)')->setParameter('searchIds', $ids);
            }
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            if (ctype_digit($category)) {
                $qb
                    ->andWhere('category.id = :categoryId')
                    ->setParameter('categoryId', (int) $category);
            } else {
                $qb
                    ->andWhere('LOWER(category.name) = :category')
                    ->setParameter('category', str_replace(['-', '_'], ' ', mb_strtolower($category)));
            }
        }

        $dateFrom = trim((string) ($filters['dateFrom'] ?? ''));
        if ($dateFrom !== '') {
            $qb
                ->andWhere('event.dateTime >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }

        $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ($dateTo !== '') {
            $qb
                ->andWhere('event.dateTime <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $locationType = trim((string) ($filters['locationType'] ?? ''));
        if ($locationType === 'online') {
            $qb->andWhere('event.isOnline = true');
        } elseif ($locationType === 'in_person') {
            $qb->andWhere('event.isOnline = false');
        }

        $organizer = trim((string) ($filters['organizer'] ?? ''));
        if ($organizer !== '' && ctype_digit($organizer)) {
            $qb
                ->andWhere('organizer.id = :organizerId')
                ->setParameter('organizerId', (int) $organizer);
        }

        $priceMin = trim((string) ($filters['priceMin'] ?? ''));
        if ($priceMin !== '' && is_numeric($priceMin)) {
            $qb
                ->andWhere('tier.basePrice >= :priceMin')
                ->setParameter('priceMin', (int) $priceMin);
        }

        $priceMax = trim((string) ($filters['priceMax'] ?? ''));
        if ($priceMax !== '' && is_numeric($priceMax)) {
            $qb
                ->andWhere('tier.basePrice <= :priceMax')
                ->setParameter('priceMax', (int) $priceMax);
        }

        $availableOnly = filter_var($filters['availableOnly'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($availableOnly) {
            $qb
                ->andWhere('event.status = :active')
                ->andWhere('event.dateTime > :now')
                ->andWhere('(tier.saleStartsAt IS NULL OR tier.saleStartsAt <= :now)')
                ->andWhere('(tier.saleEndsAt IS NULL OR tier.saleEndsAt >= :now)')
                ->andWhere('(tier.totalSeats - tier.soldCount) > 0')
                ->setParameter('active', Event::STATUS_ACTIVE)
                ->setParameter('now', $now);
        }

        return $qb;
    }
}
