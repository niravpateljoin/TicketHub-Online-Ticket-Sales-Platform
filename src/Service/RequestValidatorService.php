<?php

namespace App\Service;

use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestValidatorService
{
    public function __construct(private readonly ValidatorInterface $validator) {}

    /**
     * Populate a DTO from the JSON request body and validate it.
     * Returns a 422 JsonResponse if validation fails, or null on success.
     */
    public function validateFromRequest(Request $request, object $dto): ?JsonResponse
    {
        $body = $this->extractBody($request);

        foreach ($body as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $this->coerceValue($dto, $key, $value);
            }
        }

        return $this->validate($dto);
    }

    /**
     * Validate an already-populated DTO.
     * Returns a 422 JsonResponse if validation fails, or null on success.
     */
    public function validate(object $dto): ?JsonResponse
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) === 0) {
            return null;
        }

        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new JsonResponse(['message' => 'Validation failed.', 'errors' => $errors], 422);
    }

    private function extractBody(Request $request): array
    {
        $formBody = $request->request->all();
        if (is_array($formBody) && $formBody !== []) {
            return $formBody;
        }

        $jsonBody = json_decode($request->getContent(), true);
        return is_array($jsonBody) ? $jsonBody : [];
    }

    private function coerceValue(object $dto, string $property, mixed $value): mixed
    {
        try {
            $reflection = new ReflectionProperty($dto, $property);
            $type = $reflection->getType();
            if (!$type instanceof ReflectionNamedType) {
                return $value;
            }

            $typeName = $type->getName();
            $allowsNull = $type->allowsNull();

            if ($value === null && $allowsNull) {
                return null;
            }

            return match ($typeName) {
                'bool' => $this->toBool($value),
                'int' => is_numeric($value) ? (int) $value : 0,
                'float' => is_numeric($value) ? (float) $value : 0.0,
                'string' => is_scalar($value) || $value === null ? (string) ($value ?? '') : '',
                default => $value,
            };
        } catch (\ReflectionException) {
            return $value;
        }
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'off', 'no', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }
}
