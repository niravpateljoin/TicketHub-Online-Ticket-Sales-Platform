<?php

namespace App\Controller\Api\Organizer;

use App\Controller\Api\ApiController;
use App\Dto\Organizer\UpdateEventStatusDto;
use App\Dto\Organizer\UpsertEventDto;
use App\Entity\Booking;
use App\Entity\Category;
use App\Entity\Event;
use App\Entity\Organizer;
use App\Entity\SeatReservation;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use App\Repository\OrganizerRepository;
use App\Repository\SeatReservationRepository;
use App\Security\Voter\EventVoter;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use App\Service\EventCancellationService;
use App\Service\RequestValidatorService;
use App\Service\RateLimiter\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/organizer')]
class EventController extends ApiController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly OrganizerRepository $organizerRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly SeatReservationRepository $seatReservationRepository,
        private readonly RequestValidatorService $validator,
        private readonly EntityManagerInterface $em,
        private readonly ApiDataTransformer $transformer,
        private readonly EventCancellationService $eventCancellationService,
        private readonly CacheService $cache,
        private readonly RateLimiterService $rateLimiter,
        #[Autowire(service: 'limiter.event_create')]
        private readonly RateLimiterFactory $eventCreateLimiter,
    ) {}

    #[Route('/events', name: 'api_organizer_events_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->getCurrentOrganizer();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, (int) ($request->query->get('limit') ?? $request->query->get('perPage') ?? 10)));

        $qb = $this->eventRepository->createQueryBuilder('event')
            ->join('event.category', 'category')
            ->addSelect('category')
            ->andWhere('event.organizer = :organizer')
            ->setParameter('organizer', $organizer)
            ->orderBy('event.dateTime', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(event.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $events = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->paginated(
            array_map(fn (Event $event): array => $this->transformer->eventSummary($event), $events),
            $page,
            $total,
            $perPage
        );
    }

    #[Route('/events/{id}', name: 'api_organizer_events_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        return $this->success($this->transformer->eventDetail($event));
    }

    #[Route('/events', name: 'api_organizer_events_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->rateLimiter->consume(
            $this->eventCreateLimiter,
            sprintf('organizer_%d', (int) $user->getId()),
            message: 'Too many event creation requests.'
        );

        $dto = new UpsertEventDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $category = $this->resolveCategory($dto->category);
        if (!$category instanceof Category) {
            return $this->error('Category not found.', 422, ['category' => 'Please choose a valid category.']);
        }

        $event = new Event();
        $event->setOrganizer($this->getCurrentOrganizer());
        $this->hydrateEvent($event, $dto, $category);

        $bannerError = $this->handleBannerUpload($event, $request->files->get('bannerImage'));
        if ($bannerError instanceof JsonResponse) {
            return $bannerError;
        }

        $this->em->persist($event);
        $this->em->flush();

        $this->cache->invalidateEventsList();

        return $this->success($this->transformer->eventDetail($event), 201, 'Event created.');
    }

    #[Route('/events/{id}', name: 'api_organizer_events_update', methods: ['PUT', 'POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        $dto = new UpsertEventDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $category = $this->resolveCategory($dto->category);
        if (!$category instanceof Category) {
            return $this->error('Category not found.', 422, ['category' => 'Please choose a valid category.']);
        }

        $this->hydrateEvent($event, $dto, $category);

        $bannerError = $this->handleBannerUpload($event, $request->files->get('bannerImage'));
        if ($bannerError instanceof JsonResponse) {
            return $bannerError;
        }

        $this->em->flush();

        $this->cache->invalidateEvent((int) $event->getId());

        return $this->success($this->transformer->eventDetail($event), message: 'Event updated.');
    }

    #[Route('/events/{id}/status', name: 'api_organizer_events_status', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        $dto = new UpdateEventStatusDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        if ($dto->status === Event::STATUS_CANCELLED) {
            return $this->error('Use the cancel endpoint to cancel an event.', 422);
        }

        if ($dto->status === $event->getStatus()) {
            return $this->success($this->transformer->eventDetail($event), message: 'Event status unchanged.');
        }

        if ($event->getStatus() !== Event::STATUS_ACTIVE) {
            return $this->error('Only active events can be moved to postponed or sold out.', 422);
        }

        if (!in_array($dto->status, [Event::STATUS_POSTPONED, Event::STATUS_SOLD_OUT], true)) {
            return $this->error('Organizers can only mark an active event as postponed or sold out.', 422);
        }

        $event->setStatus($dto->status);
        $this->em->flush();

        $this->cache->invalidateEvent((int) $event->getId());

        return $this->success($this->transformer->eventDetail($event), message: 'Event status updated.');
    }

    #[Route('/events/{id}/cancel', name: 'api_organizer_events_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_CANCEL, $event);

        $result = $this->eventCancellationService->cancel($event, 'Organizer cancelled event');

        return $this->success([
            'event'           => $this->transformer->eventSummary($event),
            'usersRefunded'   => $result->usersRefunded,
            'creditsRefunded' => $result->creditsRefunded,
        ], message: 'Event cancelled.');
    }

    #[Route('/events/{id}/bookings', name: 'api_organizer_events_bookings', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function bookings(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, $request->query->getInt('perPage', 10)));

        $qb = $this->bookingRepository->createQueryBuilder('booking')
            ->join('booking.user', 'user')
            ->addSelect('user')
            ->andWhere('booking.event = :event')
            ->setParameter('event', $event)
            ->orderBy('booking.createdAt', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT booking.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $bookings = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'data' => array_map(fn (Booking $booking): array => $this->transformer->booking($booking), $bookings),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
            'message' => 'OK',
            'eventName' => $event->getName(),
        ]);
    }

    #[Route('/events/{id}', name: 'api_organizer_events_delete_legacy', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        if (count($event->getBookings()) > 0) {
            return $this->error('This event already has bookings. Cancel it instead of deleting it.', 409);
        }

        $activeReservations = (int) $this->seatReservationRepository->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->join('reservation.ticketTier', 'tier')
            ->andWhere('tier.event = :event')
            ->andWhere('reservation.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', SeatReservation::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeReservations > 0) {
            return $this->error('This event has active cart reservations and cannot be deleted.', 409);
        }

        $eventId = (int) $event->getId();
        $this->em->remove($event);
        $this->em->flush();

        $this->cache->invalidateEvent($eventId);

        return $this->success([], message: 'Event deleted.');
    }

    private function getCurrentOrganizer(): Organizer
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->organizerRepository->findOneBy(['user' => $user]);
    }

    private function resolveCategory(string $value): ?Category
    {
        if (ctype_digit($value)) {
            return $this->categoryRepository->find((int) $value);
        }

        $normalized = strtolower(str_replace(['-', '_'], ' ', trim($value)));
        foreach ($this->categoryRepository->findAll() as $category) {
            if (strtolower($category->getName()) === $normalized) {
                return $category;
            }
        }

        return null;
    }

    private function hydrateEvent(Event $event, UpsertEventDto $dto, Category $category): void
    {
        $dateTime = new \DateTime(trim($dto->startDate . ' ' . ($dto->startTime !== '' ? $dto->startTime : '00:00')));
        $preferredSlug = trim($dto->slug) !== '' ? trim($dto->slug) : $dto->name;
        $slug = $this->ensureUniqueSlug($preferredSlug, $event->getId());

        $event
            ->setName($dto->name)
            ->setSlug($slug)
            ->setDescription($dto->description !== '' ? $dto->description : null)
            ->setCategory($category)
            ->setDateTime($dateTime)
            ->setVenueName($dto->isOnline ? 'Online Event' : ($dto->venueName !== '' ? $dto->venueName : 'TBA'))
            ->setVenueAddress($dto->isOnline ? null : ($dto->venueAddress !== '' ? $dto->venueAddress : null))
            ->setIsOnline($dto->isOnline)
            ->setStatus($event->getStatus() ?: Event::STATUS_ACTIVE);
    }

    private function ensureUniqueSlug(string $source, ?int $excludeEventId = null): string
    {
        $base = $this->slugify($source);
        $candidate = $base;
        $suffix = 2;

        while (true) {
            $existing = $this->eventRepository->findOneBySlug($candidate);
            if (!$existing instanceof Event) {
                return $candidate;
            }

            if ($excludeEventId !== null && (int) $existing->getId() === (int) $excludeEventId) {
                return $candidate;
            }

            $candidate = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'event';
    }

    private const ALLOWED_BANNER_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private function handleBannerUpload(Event $event, mixed $bannerFile): ?JsonResponse
    {
        if (!$bannerFile instanceof UploadedFile) {
            return null;
        }

        $mimeType = $bannerFile->getMimeType() ?? '';
        if (!in_array($mimeType, self::ALLOWED_BANNER_MIME_TYPES, true)) {
            return $this->error('Banner must be a JPEG, PNG, or WebP image.', Response::HTTP_UNPROCESSABLE_ENTITY, ['bannerImage' => 'Only JPEG, PNG, and WebP formats are accepted.']);
        }

        if ($bannerFile->getSize() !== null && $bannerFile->getSize() > 5 * 1024 * 1024) {
            return $this->error('Banner image is too large.', Response::HTTP_UNPROCESSABLE_ENTITY, ['bannerImage' => 'Maximum allowed size is 5 MB.']);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/event-banners';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $extension = $bannerFile->guessExtension() ?: 'jpg';
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $previous = $event->getBannerImageName();

        try {
            $bannerFile->move($uploadDir, $filename);
        } catch (FileException) {
            return $this->error('Could not upload banner image right now. Please try again.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $event->setBannerImageName($filename);

        if ($previous !== null && $previous !== '' && $previous !== $filename) {
            $previousPath = $uploadDir . '/' . $previous;
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        return null;
    }
}
