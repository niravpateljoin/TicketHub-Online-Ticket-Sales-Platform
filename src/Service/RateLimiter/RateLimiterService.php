<?php

namespace App\Service\RateLimiter;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimiterService
{
    public function consume(
        RateLimiterFactory $factory,
        string $key,
        int $tokens = 1,
        string $message = 'Too many requests.'
    ): RateLimit {
        $limit = $factory->create($key)->consume($tokens);

        if (!$limit->isAccepted()) {
            $retryAfter = $this->retryAfterSeconds($limit);

            throw new TooManyRequestsHttpException(
                $retryAfter,
                sprintf('%s Try again in %d seconds.', rtrim($message, '.'), $retryAfter)
            );
        }

        return $limit;
    }

    public function retryAfterSeconds(RateLimit $limit): int
    {
        $retryAt = $limit->getRetryAfter();
        if ($retryAt === null) {
            return 60;
        }

        return max(1, $retryAt->getTimestamp() - time());
    }
}

