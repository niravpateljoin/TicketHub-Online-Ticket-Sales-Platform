<?php

namespace App\Controller\Api\Auth;

use App\Controller\Api\ApiController;
use App\Dto\Auth\UpdateProfileDto;
use App\Entity\User;
use App\Repository\OrganizerRepository;
use App\Repository\UserRepository;
use App\Service\AdministratorVerificationMailer;
use App\Service\RequestValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class MeController extends ApiController
{
    public function __construct(
        private readonly OrganizerRepository $organizerRepository,
        private readonly UserRepository $userRepository,
        private readonly RequestValidatorService $validator,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AdministratorVerificationMailer $administratorVerificationMailer,
    ) {}

    /**
     * GET /api/auth/me
     * Returns the current user's profile. Useful for refreshing state after
     * credit balance changes (e.g. after checkout).
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $approvalStatus = null;
        if (in_array('ROLE_ORGANIZER', $user->getRoles(), true)) {
            $organizer = $this->organizerRepository->findOneBy(['user' => $user]);
            $approvalStatus = $organizer?->getApprovalStatus();
        }

        return $this->success([
            'id'             => $user->getId(),
            'name'           => $user->getName(),
            'email'          => $user->getEmail(),
            'pendingEmail'   => $user->getPendingEmail(),
            'roles'          => $user->getRoles(),
            'creditBalance'  => $user->getCreditBalance(),
            'isVerified'     => $user->isVerified(),
            'approvalStatus' => $approvalStatus,
        ]);
    }

    #[Route('/me', name: 'api_auth_me_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $dto = new UpdateProfileDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $email = trim($dto->email);
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
            return $this->error('Validation failed.', 422, ['email' => 'This email is already registered.']);
        }

        if ($dto->password !== '' && mb_strlen($dto->password) < 8) {
            return $this->error('Validation failed.', 422, ['password' => 'Password must be at least 8 characters.']);
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        if ($isAdmin && $email !== $user->getEmail()) {
            return $this->error('Validation failed.', 422, ['email' => 'Administrators cannot change the current email directly. Use new email verification instead.']);
        }

        $user->setName(trim($dto->name));

        $message = 'Profile updated.';

        if ($isAdmin) {
            $newEmail = trim($dto->newEmail);

            if ($newEmail !== '' && $newEmail !== $user->getEmail()) {
                $existingEmailUser = $this->userRepository->findOneBy(['email' => $newEmail]);
                if ($existingEmailUser instanceof User && $existingEmailUser->getId() !== $user->getId()) {
                    return $this->error('Validation failed.', 422, ['newEmail' => 'This email is already registered.']);
                }

                $existingPendingUser = $this->userRepository->findOneBy(['pendingEmail' => $newEmail]);
                if ($existingPendingUser instanceof User && $existingPendingUser->getId() !== $user->getId()) {
                    return $this->error('Validation failed.', 422, ['newEmail' => 'This email is already awaiting verification on another account.']);
                }

                $user
                    ->setPendingEmail($newEmail)
                    ->setVerificationToken(bin2hex(random_bytes(32)));

                try {
                    $this->administratorVerificationMailer->sendEmailChangeVerificationEmail($user);
                } catch (TransportExceptionInterface|\RuntimeException $exception) {
                    return $this->error('New email could not be verified because the verification email failed to send.', 500);
                }

                $message = 'Profile updated. Verification email sent to the new address.';
            }
        } else {
            $user->setEmail($email);
        }

        if ($dto->password !== '') {
            $user->setPassword($this->hasher->hashPassword($user, $dto->password));
        }

        $this->em->flush();

        return $this->success([
            'id'             => $user->getId(),
            'name'           => $user->getName(),
            'email'          => $user->getEmail(),
            'pendingEmail'   => $user->getPendingEmail(),
            'roles'          => $user->getRoles(),
            'creditBalance'  => $user->getCreditBalance(),
            'isVerified'     => $user->isVerified(),
            'approvalStatus' => in_array('ROLE_ORGANIZER', $user->getRoles(), true)
                ? $this->organizerRepository->findOneBy(['user' => $user])?->getApprovalStatus()
                : null,
        ], message: $message);
    }
}
