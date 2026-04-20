<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class ApiController extends AbstractController
{
    protected function success(mixed $data, int $status = 200, string $message = 'OK'): JsonResponse
    {
        return new JsonResponse([
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $body = ['message' => $message];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status);
    }

    protected function paginated(array $data, int $page, int $total, int $perPage = 12, string $message = 'OK'): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
            'message' => $message,
        ]);
    }
}
