<?php

namespace App\Controller\Api\Cart;

use App\Controller\Api\ApiController;
use App\Dto\Cart\AddToCartDto;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Repository\TicketTierRepository;
use App\Exception\CartException;
use App\Service\CartService;
use App\Service\RequestValidatorService;
use App\Service\RateLimiter\RateLimiterService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cart')]
class CartController extends ApiController
{
    public function __construct(
        private readonly TicketTierRepository $ticketTierRepository,
        private readonly CartService $cartService,
        private readonly RequestValidatorService $validator,
        private readonly RateLimiterService $rateLimiter,
        #[Autowire(service: 'limiter.cart_add')]
        private readonly RateLimiterFactory $cartAddLimiter,
    ) {}

    #[Route('', name: 'api_cart_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->success($this->cartService->buildCartPayload($user));
    }

    #[Route('', name: 'api_cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->rateLimiter->consume(
            $this->cartAddLimiter,
            sprintf('user_%d', (int) $user->getId()),
            message: 'Too many add-to-cart attempts.'
        );

        $dto = new AddToCartDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $tier = $this->ticketTierRepository->find((int) $dto->tierId);
        if (!$tier instanceof TicketTier) {
            return $this->error('Ticket tier not found.', 404);
        }

        try {
            $this->cartService->addToCart($user, $tier, (int) $dto->quantity);
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->success(
            $this->cartService->buildCartPayload($user),
            201,
            sprintf('%d ticket(s) added to your cart. Reserved for 10 minutes.', (int) $dto->quantity)
        );
    }

    #[Route('/{reservationId}', name: 'api_cart_remove', methods: ['DELETE'], requirements: ['reservationId' => '\d+'])]
    public function remove(int $reservationId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->cartService->removeFromCart($user, $reservationId);
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->success($this->cartService->buildCartPayload($user), message: 'Cart item removed.');
    }

    #[Route('', name: 'api_cart_clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->cartService->clearCart($user);

        return $this->success($this->cartService->buildCartPayload($user), message: 'Cart cleared.');
    }
}
