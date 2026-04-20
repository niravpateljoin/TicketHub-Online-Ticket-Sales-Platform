<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Dto\Organizer\UpsertEventDto;
use App\Dto\Organizer\UpsertTierDto;
use App\Entity\Booking;
use App\Entity\Category;
use App\Entity\Event;
use App\Entity\TicketTier;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use App\Repository\TicketTierRepository;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use App\Service\EventCancellationService;
use App\Service\RequestValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/events')]
class EventController extends ApiController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TicketTierRepository $ticketTierRepository,
        private readonly RequestValidatorService $validator,
        private readonly ApiDataTransformer $transformer,
        private readonly EventCancellationService $eventCancellationService,
        private readonly CacheService $cache,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_admin_events_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, $request->query->getInt('perPage', 10)));
        $search = trim((string) $request->query->get('search', ''));

        $qb = $this->eventRepository->createQueryBuilder('event')
            ->join('event.organizer', 'organizer')
            ->join('organizer.user', 'organizerUser')
            ->join('event.category', 'category')
            ->addSelect('organizer', 'organizerUser', 'category')
            ->orderBy('event.dateTime', 'DESC');

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(event.name) LIKE :search OR LOWER(organizerUser.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

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

    #[Route('/{id}', name: 'api_admin_events_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

        return $this->success($this->transformer->eventDetail($event));
    }

    #[Route('/{id}', name: 'api_admin_events_update', methods: ['PUT', 'POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

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

    #[Route('/{id}/cancel', name: 'api_admin_events_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

        $result = $this->eventCancellationService->cancel($event, 'Admin cancelled event');

        return $this->success([
            'event'           => $this->transformer->eventSummary($event),
            'usersRefunded'   => $result->usersRefunded,
            'creditsRefunded' => $result->creditsRefunded,
        ], message: 'Event cancelled.');
    }

    #[Route('/{id}/tiers', name: 'api_admin_events_tiers_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createTier(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

        $dto = new UpsertTierDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        $tier = new TicketTier();
        $tier->setEvent($event);
        $this->hydrateTier($tier, $dto);

        $this->em->persist($tier);
        $this->em->flush();
        $this->cache->invalidateEvent((int) $event->getId());

        return $this->success($this->transformer->tier($tier), 201, 'Ticket tier added.');
    }

    #[Route('/{id}/tiers/{tierId}', name: 'api_admin_events_tiers_update', methods: ['PUT'], requirements: ['id' => '\d+', 'tierId' => '\d+'])]
    public function updateTier(int $id, int $tierId, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        $tier = $this->ticketTierRepository->find($tierId);

        if (!$event instanceof Event || !$tier instanceof TicketTier || $tier->getEvent()->getId() !== $event->getId()) {
            return $this->error('Ticket tier not found.', 404);
        }

        $dto = new UpsertTierDto();
        $error = $this->validator->validateFromRequest($request, $dto);
        if ($error !== null) {
            return $error;
        }

        if ((int) $dto->totalSeats < $tier->getSoldCount()) {
            return $this->error('Total seats cannot be less than tickets already sold.', 422);
        }

        $this->hydrateTier($tier, $dto);
        $this->em->flush();
        $this->cache->invalidateEvent((int) $event->getId());

        return $this->success($this->transformer->tier($tier), message: 'Ticket tier updated.');
    }

    #[Route('/{id}/tiers/{tierId}', name: 'api_admin_events_tiers_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'tierId' => '\d+'])]
    public function deleteTier(int $id, int $tierId): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        $tier = $this->ticketTierRepository->find($tierId);

        if (!$event instanceof Event || !$tier instanceof TicketTier || $tier->getEvent()->getId() !== $event->getId()) {
            return $this->error('Ticket tier not found.', 404);
        }

        if ($tier->getSoldCount() > 0) {
            return $this->error('Cannot delete a tier with confirmed bookings.', 409);
        }

        $this->em->remove($tier);
        $this->em->flush();
        $this->cache->invalidateEvent((int) $event->getId());

        return $this->success([], message: 'Ticket tier deleted.');
    }

    #[Route('/{id}', name: 'api_admin_events_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

        $confirmedBookings = $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Booking::class, 'b')
            ->where('b.event = :event')
            ->andWhere('b.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', Booking::STATUS_CONFIRMED)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $confirmedBookings > 0) {
            return $this->error(
                'This event has confirmed bookings. Please cancel it first to refund ticket holders, then delete.',
                422
            );
        }

        $this->em->remove($event);
        $this->em->flush();
        $this->cache->invalidateEvent($id);

        return $this->success(null, message: 'Event deleted.');
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

    private function hydrateTier(TicketTier $tier, UpsertTierDto $dto): void
    {
        $tier
            ->setName($dto->name)
            ->setBasePrice((int) $dto->price)
            ->setTotalSeats((int) $dto->totalSeats)
            ->setSaleStartsAt($dto->saleStartsAt !== '' ? new \DateTime($dto->saleStartsAt) : null)
            ->setSaleEndsAt($dto->saleEndsAt !== '' ? new \DateTime($dto->saleEndsAt) : null);
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
