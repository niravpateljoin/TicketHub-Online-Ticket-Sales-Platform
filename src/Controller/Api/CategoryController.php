<?php

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends ApiController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ApiDataTransformer $transformer,
        private readonly CacheService $cache,
    ) {}

    #[Route('/api/categories', name: 'api_categories_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $categories = $this->cache->getCategories(function (): array {
            return array_map(
                fn (array $row): array => [
                    ...$this->transformer->category($row[0]),
                    'eventCount' => (int) ($row['eventCount'] ?? 0),
                ],
                $this->categoryRepository->findPublicCategoriesWithCounts()
            );
        });

        return $this->success($categories);
    }
}
