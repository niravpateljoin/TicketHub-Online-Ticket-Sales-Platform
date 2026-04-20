<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Entity\Booking;
use App\Entity\Transaction;
use App\Message\Notification\WaitlistNotificationMessage;
use App\Message\Payment\RefundIssuedMessage;
use App\Repository\BookingRepository;
use App\Repository\WaitlistRepository;
use App\Service\ApiDataTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/bookings')]
#[IsGranted('ROLE_ADMIN')]
class BookingController extends ApiController
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly WaitlistRepository $waitlistRepository,
        private readonly ApiDataTransformer $transformer,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('', name: 'api_admin_bookings_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page    = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, $request->query->getInt('perPage', 20)));
        $status  = trim((string) $request->query->get('status', ''));
        $search  = trim((string) $request->query->get('search', ''));

        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->join('b.event', 'e')
            ->addSelect('u', 'e')
            ->orderBy('b.createdAt', 'DESC');

        if ($status !== '') {
            $qb->andWhere('b.status = :status')->setParameter('status', $status);
        }

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(u.email) LIKE :search OR LOWER(e.name) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(b.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $bookings = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->paginated(
            array_map(fn (Booking $b): array => $this->transformer->booking($b), $bookings),
            $page,
            $total,
            $perPage
        );
    }

    #[Route('/{id}/refund', name: 'api_admin_bookings_refund', methods: ['POST'])]
    public function refund(int $id): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);

        if ($booking === null) {
            return $this->error('Booking not found.', 404);
        }

        if ($booking->getStatus() !== Booking::STATUS_CONFIRMED) {
            return $this->error(sprintf('Cannot refund a booking with status "%s".', $booking->getStatus()), 422);
        }

        $user   = $booking->getUser();
        $amount = $booking->getTotalCredits();

        $this->em->wrapInTransaction(function () use ($booking, $user, $amount): void {
            $user->setCreditBalance($user->getCreditBalance() + $amount);

            $transaction = new Transaction();
            $transaction
                ->setUser($user)
                ->setAmount($amount)
                ->setType(Transaction::TYPE_REFUND)
                ->setReference(sprintf('Admin refund: booking #%d / event #%d', $booking->getId(), $booking->getEvent()->getId()));

            $this->em->persist($transaction);
            $booking->setStatus(Booking::STATUS_REFUNDED);

            // Restore sold count on each tier
            foreach ($booking->getBookingItems() as $item) {
                $tier = $item->getTicketTier();
                $tier->setSoldCount(max(0, $tier->getSoldCount() - $item->getQuantity()));
            }

            $this->em->flush();
        });

        // Dispatch payment audit message
        $this->bus->dispatch(new RefundIssuedMessage(
            userId:    (int) $user->getId(),
            amount:    $amount,
            reason:    sprintf('Admin manual refund — booking #%d', $booking->getId()),
            bookingId: (int) $booking->getId(),
        ));

        // Notify waitlisted users for freed tiers
        foreach ($booking->getBookingItems() as $item) {
            $tier    = $item->getTicketTier();
            $entries = $this->waitlistRepository->findPendingByTierOrderedByJoinDate($tier);
            foreach ($entries as $entry) {
                $entry->setStatus('notified')->setNotifiedAt(new \DateTime());
                $this->bus->dispatch(new WaitlistNotificationMessage((int) $entry->getId()));
            }
        }
        $this->em->flush();

        return $this->success(
            ['creditBalance' => $user->getCreditBalance()],
            message: sprintf('Booking #%d refunded. %d credits returned to user.', $booking->getId(), $amount)
        );
    }
}
