<?php

namespace App\Controller\Api\Event;

use App\Controller\Api\ApiController;
use App\Entity\Event;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use App\Repository\OrganizerRepository;
use App\Repository\TicketTierRepository;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use App\Service\RateLimiter\RateLimiterService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events')]
class PublicEventController extends ApiController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly OrganizerRepository $organizerRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TicketTierRepository $tierRepository,
        private readonly ApiDataTransformer $transformer,
        private readonly CacheService $cache,
        private readonly RateLimiterService $rateLimiter,
        #[Autowire(service: 'limiter.event_browse')]
        private readonly RateLimiterFactory $eventBrowseLimiter,
    ) {}

    #[Route('', name: 'api_events_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $this->rateLimiter->consume(
            $this->eventBrowseLimiter,
            $ip,
            message: 'Too many browse requests.'
        );

        $page    = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, (int) ($request->query->get('perPage') ?? 12)));
        $filters = $request->query->all();

        // Cache key is stable for the same filters + page — different filter combos get
        // separate entries, all tagged events_list so one invalidation wipes them all.
        $filterHash = md5(serialize($filters));

        $data = $this->cache->getEventsList(
            $filterHash,
            $page,
            $perPage,
            function () use ($filters, $page, $perPage): array {
                $result = $this->eventRepository->findFiltered($filters, $page, $perPage);
                return [
                    'items'    => array_map(
                        fn (Event $event): array => $this->transformer->eventSummary($event),
                        $result['items']
                    ),
                    'page'     => $result['page'],
                    'total'    => $result['total'],
                    'perPage'  => $result['perPage'],
                ];
            }
        );

        return $this->paginated($data['items'], $data['page'], $data['total'], $data['perPage']);
    }

    #[Route('/filter-options', name: 'api_events_filter_options', methods: ['GET'])]
    public function filterOptions(): JsonResponse
    {
        $categories = $this->categoryRepository->findPublicCategoriesWithCounts();
        $organizers = $this->organizerRepository->findPublicOrganizersWithCounts();

        return $this->success([
            'categories' => array_map(
                fn (array $row): array => [
                    ...$this->transformer->category($row[0]),
                    'eventCount' => (int) ($row['eventCount'] ?? 0),
                ],
                $categories
            ),
            'organizers' => array_map(
                static fn (array $row): array => [
                    'id' => $row[0]->getId(),
                    'email' => $row[0]->getUser()->getEmail(),
                    'name' => $row[0]->getUser()->getEmail(),
                    'eventCount' => (int) ($row['eventCount'] ?? 0),
                ],
                $organizers
            ),
        ]);
    }

    #[Route('/{identifier}', name: 'api_events_show', methods: ['GET'])]
    public function show(string $identifier): JsonResponse
    {
        $event = ctype_digit($identifier)
            ? $this->eventRepository->find((int) $identifier)
            : $this->eventRepository->findOneBySlug($identifier);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $eventId = (int) $event->getId();

        // Cache the full event detail array (static data: name, venue, tiers, pricing…).
        $data = $this->cache->getEventDetail(
            $eventId,
            fn (): array => $this->transformer->eventDetail($event)
        );

        // availableSeats MUST always come from DB — never from cache.
        // A single batch query refreshes all tiers at once.
        if (isset($data['tiers']) && $data['tiers'] !== []) {
            $tierIds       = array_column($data['tiers'], 'id');
            $availableMap  = $this->tierRepository->getAvailableSeatsByIds($tierIds);

            foreach ($data['tiers'] as &$tier) {
                $tier['availableSeats'] = $availableMap[$tier['id']] ?? 0;
            }
            unset($tier);
        }

        return $this->success($data);
    }
}
