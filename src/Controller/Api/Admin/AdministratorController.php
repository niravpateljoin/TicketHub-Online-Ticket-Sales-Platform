<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Dto\Admin\CreateAdministratorDto;
use App\Dto\Admin\UpdateAdministratorDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdministratorVerificationMailer;
use App\Service\ApiDataTransformer;
use App\Service\RequestValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/administrators')]
class AdministratorController extends ApiController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly ApiDataTransformer $transformer,
        private readonly RequestValidatorService $validator,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AdministratorVerificationMailer $administratorVerificationMailer,
    ) {}

    #[Route('', name: 'api_admin_administrators_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, (int) ($request->query->get('limit') ?? $request->query->get('perPage') ?? 10)));
        $search = trim((string) $request->query->get('search', ''));

        $qb = $this->userRepository->createQueryBuilder('user')
            ->andWhere('user.role = :role')
            ->setParameter('role', 'ROLE_ADMIN')
            ->orderBy('user.createdAt', 'DESC');

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(user.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(user.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $administrators = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->paginated(
            array_map(fn (User $user): array => $this->transformer->user($user), $administrators),
            $page,
            $total,
            $perPage
        );
    }

    #[Route('', name: 'api_admin_administrators_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $dto = new CreateAdministratorDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $email = trim($dto->email);
        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            return $this->error('Validation failed.', 422, ['email' => 'This email is already registered.']);
        }

        $administrator = new User();
        $administrator
            ->setEmail($email)
            ->setPassword($this->hasher->hashPassword($administrator, $dto->password))
            ->setRole('ROLE_ADMIN')
            ->setCreditBalance(0);
        $this->administratorVerificationMailer->initializePendingVerification($administrator);

        $this->em->persist($administrator);
        $this->em->flush();

        try {
            $this->administratorVerificationMailer->sendVerificationEmail($administrator);
        } catch (TransportExceptionInterface|\RuntimeException $exception) {
            $this->em->remove($administrator);
            $this->em->flush();

            return $this->error('Administrator could not be created because the verification email failed to send.', 500);
        }

        return $this->success(
            $this->transformer->user($administrator),
            201,
            'Administrator created. Verification email sent.'
        );
    }

    #[Route('/{id}', name: 'api_admin_administrators_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $administrator = $this->userRepository->findOneBy(['id' => $id, 'role' => 'ROLE_ADMIN']);
        if (!$administrator instanceof User) {
            return $this->error('Administrator not found.', 404);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() === $administrator->getId()) {
            return $this->error('You cannot edit your own administrator account from this module.', 409);
        }

        $dto = new UpdateAdministratorDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $email = trim($dto->email);
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser instanceof User && $existingUser->getId() !== $administrator->getId()) {
            return $this->error('Validation failed.', 422, ['email' => 'This email is already registered.']);
        }

        if ($dto->password !== '' && mb_strlen($dto->password) < 8) {
            return $this->error('Validation failed.', 422, ['password' => 'Password must be at least 8 characters.']);
        }

        $administrator->setEmail($email);

        if ($dto->password !== '') {
            $administrator->setPassword($this->hasher->hashPassword($administrator, $dto->password));
        }

        $this->em->flush();

        return $this->success(
            $this->transformer->user($administrator),
            message: 'Administrator updated.'
        );
    }

    #[Route('/{id}/resend-verification', name: 'api_admin_administrators_resend_verification', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resendVerification(int $id): JsonResponse
    {
        $administrator = $this->userRepository->findOneBy(['id' => $id, 'role' => 'ROLE_ADMIN']);
        if (!$administrator instanceof User) {
            return $this->error('Administrator not found.', 404);
        }

        if ($administrator->isVerified()) {
            return $this->error('Administrator is already verified.', 409);
        }

        if ($administrator->getVerificationToken() === null) {
            $this->administratorVerificationMailer->initializePendingVerification($administrator);
            $this->em->flush();
        }

        try {
            $this->administratorVerificationMailer->sendVerificationEmail($administrator);
        } catch (TransportExceptionInterface|\RuntimeException $exception) {
            return $this->error('Verification email could not be sent.', 500);
        }

        return $this->success(
            $this->transformer->user($administrator),
            message: 'Verification email sent.'
        );
    }

    #[Route('/{id}', name: 'api_admin_administrators_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $administrator = $this->userRepository->findOneBy(['id' => $id, 'role' => 'ROLE_ADMIN']);
        if (!$administrator instanceof User) {
            return $this->error('Administrator not found.', 404);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser->getId() === $administrator->getId()) {
            return $this->error('You cannot delete your own administrator account.', 409);
        }

        if ($this->countAdministrators() <= 1) {
            return $this->error('At least one administrator account must remain.', 409);
        }

        $this->em->remove($administrator);
        $this->em->flush();

        return $this->success([], message: 'Administrator deleted.');
    }

    private function countAdministrators(): int
    {
        return (int) $this->userRepository->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->andWhere('user.role = :role')
            ->setParameter('role', 'ROLE_ADMIN')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
