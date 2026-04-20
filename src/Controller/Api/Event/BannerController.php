<?php

namespace App\Controller\Api\Event;

use App\Controller\Api\ApiController;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class BannerController extends ApiController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    #[Route('/api/events/{id}/banner', name: 'api_events_banner', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function __invoke(int $id): BinaryFileResponse|\Symfony\Component\HttpFoundation\JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if ($event === null || $event->getBannerImageName() === null) {
            return $this->error('Banner not found.', 404);
        }

        $path = sprintf('%s/public/uploads/event-banners/%s', $this->getParameter('kernel.project_dir'), $event->getBannerImageName());

        if (!is_file($path)) {
            return $this->error('Banner not found.', 404);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

        return $response;
    }
}
