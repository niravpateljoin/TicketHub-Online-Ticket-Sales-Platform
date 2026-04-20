<?php

namespace App\Controller\Api\Auth;

use App\Controller\Api\ApiController;
use App\Dto\Auth\RegisterOrganizerDto;
use App\Dto\Auth\RegisterUserDto;
use App\Entity\Organizer;
use App\Entity\User;
use App\Message\Notification\SendVerificationEmailMessage;
use App\Repository\UserRepository;
use App\Service\AdministratorVerificationMailer;
use App\Service\RequestValidatorService;
use App\Service\RateLimiter\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class RegisterController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AdministratorVerificationMailer $verificationMailer,
        private readonly RequestValidatorService     $validator,
        private readonly UserRepository              $userRepository,
        private readonly MessageBusInterface         $bus,
        private readonly RateLimiterService          $rateLimiter,
        #[Autowire(service: 'limiter.register_user')]
        private readonly RateLimiterFactory          $registerUserLimiter,
        #[Autowire(service: 'limiter.register_organizer')]
        private readonly RateLimiterFactory          $registerOrganizerLimiter,
    ) {}

    /**
     * POST /api/auth/register
     * Register a regular user. Email must be verified before login.
     */
    #[Route('/register', name: 'api_auth_register_user', methods: ['POST'])]
    public function registerUser(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $this->rateLimiter->consume($this->registerUserLimiter, $ip, message: 'Too many registration attempts.');

        $dto = new RegisterUserDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        // Check email uniqueness
        if ($this->userRepository->findOneBy(['email' => $dto->email]) !== null) {
            return $this->error('Validation failed.', 422, ['email' => 'This email is already registered.']);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->hasher->hashPassword($user, $dto->password));
        $user->setRole('ROLE_USER');
        $this->verificationMailer->initializePendingVerification($user);

        $this->em->persist($user);
        $this->em->flush();

        // Dispatch verification email asynchronously to keep registration response fast.
        $this->bus->dispatch(new SendVerificationEmailMessage((int) $user->getId()));

        return $this->success([
            'email' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
        ], 201, 'Registration successful. Verification email is being sent.');
    }

    /**
     * POST /api/auth/register/organizer
     * Register an organizer. Account is created with status=pending.
     * No JWT is issued — they must wait for admin approval before logging in.
     */
    #[Route('/register/organizer', name: 'api_auth_register_organizer', methods: ['POST'])]
    public function registerOrganizer(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $this->rateLimiter->consume($this->registerOrganizerLimiter, $ip, message: 'Too many organizer registration attempts.');

        $dto = new RegisterOrganizerDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        // Check email uniqueness
        if ($this->userRepository->findOneBy(['email' => $dto->email]) !== null) {
            return $this->error('Validation failed.', 422, ['email' => 'This email is already registered.']);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->hasher->hashPassword($user, $dto->password));
        $user->setRole('ROLE_ORGANIZER');
        $this->verificationMailer->initializePendingVerification($user);

        if ($dto->organizationName !== '') {
            $user->setName($dto->organizationName);
        }

        $organizer = new Organizer();
        $organizer->setUser($user);
        // approvalStatus defaults to 'pending' in the entity constructor

        $this->em->persist($user);
        $this->em->persist($organizer);
        $this->em->flush();

        $this->bus->dispatch(new SendVerificationEmailMessage((int) $user->getId()));

        return $this->success(
            ['email' => $user->getEmail()],
            201,
            'Registration submitted. A verification email has been sent. Awaiting admin approval.'
        );
    }
}
