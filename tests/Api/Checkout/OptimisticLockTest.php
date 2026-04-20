<?php

namespace App\Tests\Api\Checkout;

use App\Entity\TicketTier;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final class OptimisticLockTest extends ApiTestCase
{
    public function test_only_one_checkout_succeeds_for_last_seat(): void
    {
        $tier = $this->em->getRepository(TicketTier::class)->findOneBy(['name' => TestFixtures::TIER_LAST_SEAT]);
        self::assertSame(1, $tier->getTotalSeats());
        self::assertSame(0, $tier->getSoldCount());

        // Two separate EM instances simulate two concurrent transactions
        $container = static::getContainer();
        /** @var EntityManagerInterface $em1 */
        $em1 = $container->get(EntityManagerInterface::class);
        /** @var EntityManagerInterface $em2 */
        $em2 = clone $em1;

        // Both read version = 1 (initial)
        /** @var TicketTier $tier1 */
        $tier1 = $em1->find(TicketTier::class, $tier->getId());
        /** @var TicketTier $tier2 */
        $tier2 = $em2->find(TicketTier::class, $tier->getId());

        $result1 = null;
        $result2 = null;

        // EM1 commits first
        $em1->getConnection()->beginTransaction();
        $tier1->setSoldCount($tier1->getSoldCount() + 1);
        $em1->flush();
        $em1->getConnection()->commit();
        $result1 = 'success';

        // EM2 tries to commit — version is stale → OptimisticLockException
        try {
            $em2->getConnection()->beginTransaction();
            $em2->lock($tier2, LockMode::OPTIMISTIC, $tier2->getVersion());
            $tier2->setSoldCount($tier2->getSoldCount() + 1);
            $em2->flush();
            $em2->getConnection()->commit();
            $result2 = 'success';
        } catch (OptimisticLockException) {
            $em2->getConnection()->rollBack();
            $result2 = 'conflict';
        }

        self::assertSame('success', $result1);
        self::assertSame('conflict', $result2);

        $this->em->clear();
        $final = $this->em->getRepository(TicketTier::class)->find($tier->getId());
        self::assertSame(1, $final->getSoldCount());
    }
}
