<?php

namespace App\Repository;

use App\Entity\TicketTier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketTier>
 */
class TicketTierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketTier::class);
    }

    /**
     * Returns available seats for each given tier ID in a single query.
     * Used to refresh availableSeats after loading event detail from cache —
     * this field must never come from cache (race-condition safety).
     *
     * @param  int[]        $tierIds
     * @return array<int,int>  [tierId => availableSeats]
     */
    public function getAvailableSeatsByIds(array $tierIds): array
    {
        if ($tierIds === []) {
            return [];
        }

        // One query: for each tier sum the totalSeats - soldCount - active reservations.
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT
                t.id,
                GREATEST(0, t.total_seats - t.sold_count - COALESCE(r.reserved, 0)) AS available
             FROM ticket_tier t
             LEFT JOIN (
                 SELECT ticket_tier_id, SUM(quantity) AS reserved
                 FROM seat_reservation
                 WHERE status = \'pending\'
                   AND expires_at > NOW()
                 GROUP BY ticket_tier_id
             ) r ON r.ticket_tier_id = t.id
             WHERE t.id IN (' . implode(',', array_fill(0, count($tierIds), '?')) . ')',
            array_values($tierIds)
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (int) $row['available'];
        }

        return $map;
    }
}
