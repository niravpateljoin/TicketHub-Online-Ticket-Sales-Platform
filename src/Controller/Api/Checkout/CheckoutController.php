<?php

namespace App\Controller\Api\Checkout;

use App\Controller\Api\ApiController;
use App\Entity\User;
use App\Exception\CartException;
use App\Service\CartService;
use App\Service\CheckoutService;
use App\Service\RateLimiter\RateLimiterService;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/checkout')]
class CheckoutController extends ApiController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly RateLimiterService $rateLimiter,
        #[Autowire(service: 'limiter.checkout')]
        private readonly RateLimiterFactory $checkoutLimiter,
    ) {}

    /**
     * GET /api/checkout
     * Returns cart summary + a fresh idempotency key for the frontend to embed in the confirm request.
     */
    #[Route('', name: 'api_checkout_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->cartService->getCheckoutContext($user);
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->success($this->cartService->buildCartPayload($user, bin2hex(random_bytes(16))));
    }

    /**
     * POST /api/checkout/confirm  (also accepts POST /api/checkout for legacy clients)
     *
     * Expected body or header:
     *   - Header: Idempotency-Key: <uuid>
     *   - OR body: { "idempotencyKey": "<uuid>" }
     *
     * The key must be generated client-side per checkout session (UUID v4).
     * The GET /api/checkout summary endpoint provides a pre-generated key as a convenience.
     */
    #[Route('', name: 'api_checkout_process_legacy', methods: ['POST'])]
    #[Route('/confirm', name: 'api_checkout_process', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Resolve idempotency key: header takes precedence, then body, then auto-generate.
        $body            = json_decode($request->getContent(), true) ?? [];
        $idempotencyKey  = trim((string) ($request->headers->get('Idempotency-Key') ?: ($body['idempotencyKey'] ?? '')));
        if ($idempotencyKey === '') {
            $idempotencyKey = bin2hex(random_bytes(16));
        }

        $rateLimit = $this->rateLimiter->consume(
            $this->checkoutLimiter,
            sprintf('user_%d', (int) $user->getId()),
            message: 'Too many checkout attempts.'
        );

        try {
            $result = $this->checkoutService->process($user, $idempotencyKey);
        } catch (CartException $exception) {
            return $this->withRateLimitHeaders(
                $this->error($exception->getMessage(), $exception->getStatusCode()),
                $rateLimit,
                5
            );
        } catch (OptimisticLockException) {
            return $this->withRateLimitHeaders(
                $this->error('Ticket availability changed. Please review your cart and try again.', 409),
                $rateLimit,
                5
            );
        }

        if ($result['alreadyProcessed']) {
            return $this->withRateLimitHeaders($this->success(
                ['bookingId' => $result['bookingId'], 'alreadyProcessed' => true],
                message: 'This checkout was already processed.'
            ), $rateLimit, 5);
        }

        return $this->withRateLimitHeaders($this->success($result, 201, 'Booking confirmed.'), $rateLimit, 5);
    }

    private function withRateLimitHeaders(JsonResponse $response, RateLimit $limit, int $maxLimit): JsonResponse
    {
        $response->headers->set('X-RateLimit-Limit', (string) $maxLimit);
        $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
        $response->headers->set('X-RateLimit-Reset', (string) ($limit->getRetryAfter()?->getTimestamp() ?? 0));

        return $response;
    }
}
