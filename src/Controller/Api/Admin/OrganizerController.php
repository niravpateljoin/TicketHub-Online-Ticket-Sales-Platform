<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Entity\Organizer;
use App\Message\Notification\OrganizerApprovedMessage;
use App\Message\Notification\OrganizerRejectedMessage;
use App\Repository\OrganizerRepository;
use App\Service\ApiDataTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/organizers')]
class OrganizerController extends ApiController
{
    public function __construct(
        private readonly OrganizerRepository $organizerRepository,
        private readonly EntityManagerInterface $em,
        private readonly ApiDataTransformer $transformer,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('', name: 'api_admin_organizers_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $status = trim((string) $request->query->get('status', 'pending'));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, (int) ($request->query->get('limit') ?? $request->query->get('perPage') ?? 10)));

        $qb = $this->organizerRepository->createQueryBuilder('organizer')
            ->join('organizer.user', 'user')
            ->addSelect('user');

        $this->applyStatusFilter($qb, $status);

        $total = (int) (clone $qb)
            ->select('COUNT(organizer.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $organizers = $qb
            ->orderBy('user.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'data' => array_map(fn (Organizer $organizer): array => $this->transformer->organizer($organizer), $organizers),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
            'counts' => $this->buildCounts(),
            'message' => 'OK',
        ]);
    }

    #[Route('/{id}/approve', name: 'api_admin_organizers_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(int $id): JsonResponse
    {
        $organizer = $this->organizerRepository->find($id);

        if (!$organizer instanceof Organizer) {
            return $this->error('Organizer not found.', 404);
        }

        $wasApproved = $organizer->getApprovalStatus() === Organizer::STATUS_APPROVED;

        $organizer
            ->setApprovalStatus(Organizer::STATUS_APPROVED)
            ->setApprovedAt(new \DateTime())
            ->setDeactivatedAt(null);

        $this->em->flush();

        if (!$wasApproved) {
            $this->bus->dispatch(new OrganizerApprovedMessage(
                (int) $organizer->getId(),
                $organizer->getUser()->getEmail()
            ));
        }

        return $this->success($this->transformer->organizer($organizer), message: 'Organizer approved.');
    }

    #[Route('/{id}/reject', name: 'api_admin_organizers_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(int $id): JsonResponse
    {
        $organizer = $this->organizerRepository->find($id);

        if (!$organizer instanceof Organizer) {
            return $this->error('Organizer not found.', 404);
        }

        $wasRejected = $organizer->getApprovalStatus() === Organizer::STATUS_REJECTED;

        $organizer
            ->setApprovalStatus(Organizer::STATUS_REJECTED)
            ->setDeactivatedAt(null);

        $this->em->flush();

        if (!$wasRejected) {
            $this->bus->dispatch(new OrganizerRejectedMessage(
                (int) $organizer->getId(),
                $organizer->getUser()->getEmail()
            ));
        }

        return $this->success($this->transformer->organizer($organizer), message: 'Organizer rejected.');
    }

    #[Route('/{id}/deactivate', name: 'api_admin_organizers_deactivate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deactivate(int $id): JsonResponse
    {
        $organizer = $this->organizerRepository->find($id);

        if (!$organizer instanceof Organizer) {
            return $this->error('Organizer not found.', 404);
        }

        $organizer->setDeactivatedAt(new \DateTime());
        $this->em->flush();

        return $this->success($this->transformer->organizer($organizer), message: 'Organizer deactivated.');
    }

    #[Route('/{id}/reactivate', name: 'api_admin_organizers_reactivate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reactivate(int $id): JsonResponse
    {
        $organizer = $this->organizerRepository->find($id);

        if (!$organizer instanceof Organizer) {
            return $this->error('Organizer not found.', 404);
        }

        $organizer
            ->setApprovalStatus(Organizer::STATUS_APPROVED)
            ->setDeactivatedAt(null);

        if ($organizer->getApprovedAt() === null) {
            $organizer->setApprovedAt(new \DateTime());
        }

        $this->em->flush();

        return $this->success($this->transformer->organizer($organizer), message: 'Organizer reactivated.');
    }

    private function applyStatusFilter(\Doctrine\ORM\QueryBuilder $qb, string $status): void
    {
        switch ($status) {
            case 'approved':
                $qb
                    ->andWhere('organizer.approvalStatus = :status')
                    ->andWhere('organizer.deactivatedAt IS NULL')
                    ->setParameter('status', Organizer::STATUS_APPROVED);
                break;
            case 'rejected':
                $qb
                    ->andWhere('organizer.approvalStatus = :status')
                    ->setParameter('status', Organizer::STATUS_REJECTED);
                break;
            case 'deactivated':
                $qb->andWhere('organizer.deactivatedAt IS NOT NULL');
                break;
            case 'pending':
            default:
                $qb
                    ->andWhere('organizer.approvalStatus = :status')
                    ->setParameter('status', Organizer::STATUS_PENDING);
                break;
        }
    }

    private function buildCounts(): array
    {
        return [
            'pending' => (int) $this->organizerRepository->createQueryBuilder('organizer')
                ->select('COUNT(organizer.id)')
                ->andWhere('organizer.approvalStatus = :status')
                ->setParameter('status', Organizer::STATUS_PENDING)
                ->getQuery()
                ->getSingleScalarResult(),
            'approved' => (int) $this->organizerRepository->createQueryBuilder('organizer')
                ->select('COUNT(organizer.id)')
                ->andWhere('organizer.approvalStatus = :status')
                ->andWhere('organizer.deactivatedAt IS NULL')
                ->setParameter('status', Organizer::STATUS_APPROVED)
                ->getQuery()
                ->getSingleScalarResult(),
            'rejected' => (int) $this->organizerRepository->createQueryBuilder('organizer')
                ->select('COUNT(organizer.id)')
                ->andWhere('organizer.approvalStatus = :status')
                ->setParameter('status', Organizer::STATUS_REJECTED)
                ->getQuery()
                ->getSingleScalarResult(),
            'deactivated' => (int) $this->organizerRepository->createQueryBuilder('organizer')
                ->select('COUNT(organizer.id)')
                ->andWhere('organizer.deactivatedAt IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }
}
