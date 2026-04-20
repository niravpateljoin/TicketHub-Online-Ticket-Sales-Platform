<?php

namespace App\EventListener;

use App\Entity\ErrorLog;
use App\Entity\User;
use App\Exception\AppException;
use App\Exception\Validation\RequestValidationException;
use App\Exception\Domain\DuplicateBookingException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ApiExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
        #[Autowire('%kernel.environment%')] private readonly string $appEnv,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();
        [$status, $body] = $this->buildResponse($e, $request->getPathInfo());

        $this->logError($e, $request->getPathInfo(), $request->getMethod(), $status);

        if ($status >= 500) {
            $this->persistErrorLog($e, $request->getPathInfo(), $request->getMethod(), $status, $request->getClientIp());
        }

        $response = new JsonResponse($body, $status);

        if ($e instanceof TooManyRequestsHttpException) {
            $response->headers->add($e->getHeaders());
        }

        $event->setResponse($response);
        $event->stopPropagation();
    }

    private function buildResponse(\Throwable $e, string $path): array
    {
        if ($e instanceof AppException) {
            $body = [
                'message'   => $e->getMessage(),
                'errorCode' => $e->getErrorCode(),
            ];

            if ($e instanceof RequestValidationException) {
                $body['errors'] = $e->getFieldErrors();
            }

            if ($e instanceof DuplicateBookingException) {
                $body['data'] = ['bookingId' => $e->existingBookingId];
            }

            return [$e->getHttpStatus(), $body];
        }

        if ($e instanceof AccessDeniedException) {
            return [403, ['message' => 'Access denied.', 'errorCode' => 'ACCESS_DENIED']];
        }

        if ($e instanceof OptimisticLockException) {
            return [409, ['message' => 'This tier just sold out. Please try again.', 'errorCode' => 'OPTIMISTIC_LOCK_CONFLICT']];
        }

        if ($e instanceof JWTDecodeFailureException) {
            return [401, ['message' => 'Invalid or expired token.', 'errorCode' => 'INVALID_TOKEN']];
        }

        if ($e instanceof TooManyRequestsHttpException) {
            $retryAfterHeader = $e->getHeaders()['Retry-After'] ?? null;
            $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : null;

            return [429, [
                'message'    => $e->getMessage() !== '' ? $e->getMessage() : 'Too many requests.',
                'errorCode'  => 'TOO_MANY_REQUESTS',
                'retryAfter' => $retryAfter,
            ]];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            return [$status, [
                'message'   => $e->getMessage() !== '' ? $e->getMessage() : $this->defaultMessage($status),
                'errorCode' => 'HTTP_' . $status,
            ]];
        }

        $message = $this->appEnv === 'dev'
            ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            : 'An internal server error occurred. Our team has been notified.';

        return [500, ['message' => $message, 'errorCode' => 'INTERNAL_ERROR']];
    }

    private function logError(\Throwable $e, string $route, string $method, int $status): void
    {
        $token = $this->tokenStorage->getToken();
        $user  = $token?->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $context = [
            'status'    => $status,
            'route'     => $route,
            'method'    => $method,
            'user_id'   => $userId,
            'exception' => get_class($e),
        ];

        if ($status >= 500) {
            $this->logger->error($e->getMessage(), $context + ['trace' => $e->getTraceAsString()]);
        } elseif ($status >= 400) {
            $this->logger->warning($e->getMessage(), $context);
        }
    }

    private function persistErrorLog(\Throwable $e, string $route, string $method, int $status, ?string $ip): void
    {
        try {
            $token  = $this->tokenStorage->getToken();
            $user   = $token?->getUser();
            $userId = $user instanceof User ? $user->getId() : null;

            $log = new ErrorLog();
            $log->setMessage(mb_substr($e->getMessage(), 0, 500));
            $log->setExceptionClass(get_class($e));
            $log->setStackTrace($e->getTraceAsString());
            $log->setRoute($route);
            $log->setMethod($method);
            $log->setStatusCode($status);
            $log->setUserId($userId);
            $log->setIpAddress($ip);
            $log->setOccurredAt(new \DateTimeImmutable());

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // Never let error logging itself crash the response
        }
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'Access denied.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            409 => 'Conflict.',
            422 => 'Validation failed.',
            429 => 'Too many requests.',
            500 => 'Internal server error.',
            default => 'Unexpected error.',
        };
    }
}
