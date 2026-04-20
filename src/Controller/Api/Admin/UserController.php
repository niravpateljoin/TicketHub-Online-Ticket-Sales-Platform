<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiDataTransformer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/users')]
class UserController extends ApiController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApiDataTransformer $transformer,
    ) {}

    #[Route('', name: 'api_admin_users_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page    = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, (int) ($request->query->get('perPage') ?? $request->query->get('limit') ?? 15)));
        $search  = trim((string) $request->query->get('search', ''));

        $qb = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.role = :role')
            ->setParameter('role', 'ROLE_USER')
            ->orderBy('u.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :search OR LOWER(u.name) LIKE :search')
               ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $users = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->paginated(
            array_map(fn (User $user): array => $this->transformer->user($user), $users),
            $page,
            $total,
            $perPage,
        );
    }
}
