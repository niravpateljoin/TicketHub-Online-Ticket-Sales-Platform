<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginRateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.login_short')]
        private readonly RateLimiterFactory $loginShortLimiter,
        #[Autowire(service: 'limiter.login_long')]
        private readonly RateLimiterFactory $loginLongLimiter,
    ) {}

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $ip = $event->getRequest()->getClientIp() ?? 'unknown';

        $shortLimit = $this->loginShortLimiter->create($ip)->consume(1);
        $longLimit = $this->loginLongLimiter->create($ip)->consume(1);

        if ($shortLimit->isAccepted() && $longLimit->isAccepted()) {
            return;
        }

        $shortRetry = $shortLimit->getRetryAfter()?->getTimestamp() ?? time() + 60;
        $longRetry = $longLimit->getRetryAfter()?->getTimestamp() ?? time() + 60;
        $retryAfter = max(1, max($shortRetry, $longRetry) - time());

        $event->setResponse(new JsonResponse([
            'message' => sprintf('Too many login attempts. Try again in %d seconds.', $retryAfter),
            'retryAfter' => $retryAfter,
        ], 429, [
            'Retry-After' => (string) $retryAfter,
        ]));
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $ip = $event->getRequest()->getClientIp() ?? 'unknown';
        $this->loginShortLimiter->create($ip)->reset();
    }
}

