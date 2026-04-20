<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Dto\Admin\ResolveErrorLogDto;
use App\Entity\ErrorLog;
use App\Repository\ErrorLogRepository;
use App\Service\ApiDataTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/error-logs')]
class ErrorLogController extends ApiController
{
    public function __construct(
        private readonly ErrorLogRepository $errorLogRepository,
        private readonly ApiDataTransformer $transformer,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_admin_error_logs_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page    = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, (int) ($request->query->get('limit') ?? $request->query->get('perPage') ?? 10)));

        $qb = $this->errorLogRepository->createQueryBuilder('log')
            ->orderBy('log.occurredAt', 'DESC');

        $route = trim((string) $request->query->get('route', ''));
        if ($route !== '') {
            $qb->andWhere('log.route LIKE :route')->setParameter('route', '%' . $route . '%');
        }

        $dateFrom = trim((string) $request->query->get('dateFrom', ''));
        if ($dateFrom !== '') {
            $qb->andWhere('log.occurredAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom . ' 00:00:00'));
        }

        $dateTo = trim((string) $request->query->get('dateTo', ''));
        if ($dateTo !== '') {
            $qb->andWhere('log.occurredAt <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        if ($request->query->getBoolean('unresolvedOnly', false)) {
            $qb->andWhere('log.resolved = false');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(log.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $logs = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->paginated(
            array_map(fn (ErrorLog $log): array => $this->transformer->errorLog($log), $logs),
            $page,
            $total,
            $perPage
        );
    }

    #[Route('/{id}', name: 'api_admin_error_logs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $log = $this->errorLogRepository->find($id);

        if (!$log instanceof ErrorLog) {
            return $this->error('Error log not found.', 404);
        }

        return $this->success($this->transformer->errorLog($log));
    }

    #[Route('/{id}/resolve', name: 'api_admin_error_logs_resolve', methods: ['POST', 'PATCH'], requirements: ['id' => '\d+'])]
    public function resolve(int $id, Request $request): JsonResponse
    {
        $log = $this->errorLogRepository->find($id);

        if (!$log instanceof ErrorLog) {
            return $this->error('Error log not found.', 404);
        }

        $data = json_decode((string) $request->getContent(), true) ?? [];
        $note = trim((string) ($data['note'] ?? $data['adminNote'] ?? ''));

        $log->setResolved(true);
        if ($note !== '') {
            $log->setAdminNote($note);
        }

        $this->em->flush();

        return $this->success($this->transformer->errorLog($log), 200, 'Error log marked as resolved.');
    }

    #[Route('/clear', name: 'api_admin_error_logs_clear', methods: ['DELETE'])]
    public function clear(Request $request): JsonResponse
    {
        $olderThanDays = max(1, $request->query->getInt('olderThanDays', 30));
        $cutoff = new \DateTimeImmutable("-{$olderThanDays} days");

        $deleted = $this->errorLogRepository->createQueryBuilder('log')
            ->delete()
            ->where('log.resolved = true')
            ->andWhere('log.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return $this->success(['deleted' => $deleted], 200, "Deleted {$deleted} resolved error log(s).");
    }
}
