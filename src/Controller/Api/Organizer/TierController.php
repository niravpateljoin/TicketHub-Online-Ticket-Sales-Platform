<?php

namespace App\Controller\Api\Organizer;

use App\Controller\Api\ApiController;
use App\Dto\Organizer\UpsertTierDto;
use App\Entity\Event;
use App\Entity\TicketTier;
use App\Repository\EventRepository;
use App\Repository\TicketTierRepository;
use App\Security\Voter\EventVoter;
use App\Service\ApiDataTransformer;
use App\Service\RequestValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/organizer/events/{id}/tiers', requirements: ['id' => '\d+'])]
class TierController extends ApiController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly TicketTierRepository $ticketTierRepository,
        private readonly RequestValidatorService $validator,
        private readonly EntityManagerInterface $em,
        private readonly ApiDataTransformer $transformer,
    ) {}

    #[Route('', name: 'api_organizer_tiers_create', methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

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

        return $this->success($this->transformer->tier($tier), 201, 'Ticket tier added.');
    }

    #[Route('/{tierId}', name: 'api_organizer_tiers_update', methods: ['PUT'], requirements: ['tierId' => '\d+'])]
    public function update(int $id, int $tierId, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        $tier = $this->ticketTierRepository->find($tierId);

        if (!$event instanceof Event || !$tier instanceof TicketTier || $tier->getEvent()->getId() !== $event->getId()) {
            return $this->error('Ticket tier not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

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

        return $this->success($this->transformer->tier($tier), message: 'Ticket tier updated.');
    }

    #[Route('/{tierId}', name: 'api_organizer_tiers_delete', methods: ['DELETE'], requirements: ['tierId' => '\d+'])]
    public function delete(int $id, int $tierId): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        $tier = $this->ticketTierRepository->find($tierId);

        if (!$event instanceof Event || !$tier instanceof TicketTier || $tier->getEvent()->getId() !== $event->getId()) {
            return $this->error('Ticket tier not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        if ($tier->getSoldCount() > 0) {
            return $this->error('Cannot delete a tier with confirmed bookings.', 409);
        }

        $this->em->remove($tier);
        $this->em->flush();

        return $this->success([], message: 'Ticket tier deleted.');
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
}
