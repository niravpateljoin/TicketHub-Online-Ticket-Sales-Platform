<?php

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiController;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/categories')]
class CategoryController extends ApiController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $em,
        private readonly ApiDataTransformer $transformer,
        private readonly CacheService $cache,
    ) {}

    #[Route('', name: 'api_admin_categories_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);

        return $this->success(array_map(
            fn (Category $category): array => $this->transformer->categoryWithCount($category),
            $categories
        ));
    }

    #[Route('', name: 'api_admin_categories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return $this->error('Category name is required.', 422);
        }

        if (strlen($name) > 100) {
            return $this->error('Category name must be 100 characters or fewer.', 422);
        }

        $existing = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($existing !== null) {
            return $this->error('A category with this name already exists.', 422);
        }

        $category = new Category();
        $category->setName($name);

        $this->em->persist($category);
        $this->em->flush();

        $this->cache->invalidateCategories();

        return $this->success($this->transformer->categoryWithCount($category), 201, 'Category created.');
    }

    #[Route('/{id}', name: 'api_admin_categories_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category instanceof Category) {
            return $this->error('Category not found.', 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return $this->error('Category name is required.', 422);
        }

        if (strlen($name) > 100) {
            return $this->error('Category name must be 100 characters or fewer.', 422);
        }

        $existing = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($existing !== null && $existing->getId() !== $category->getId()) {
            return $this->error('A category with this name already exists.', 422);
        }

        $category->setName($name);
        $this->em->flush();

        $this->cache->invalidateCategories();

        return $this->success($this->transformer->categoryWithCount($category), message: 'Category updated.');
    }

    #[Route('/{id}', name: 'api_admin_categories_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category instanceof Category) {
            return $this->error('Category not found.', 404);
        }

        if ($category->getEvents()->count() > 0) {
            return $this->error(
                sprintf('Cannot delete "%s" — it has %d event(s) assigned to it.', $category->getName(), $category->getEvents()->count()),
                422
            );
        }

        $this->em->remove($category);
        $this->em->flush();

        $this->cache->invalidateCategories();

        return $this->success(null, message: 'Category deleted.');
    }
}
