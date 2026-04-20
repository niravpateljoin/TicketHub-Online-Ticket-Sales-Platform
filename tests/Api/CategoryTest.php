<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class CategoryTest extends ApiTestCase
{
    public function test_categories_returns_public_categories(): void
    {
        $data = $this->jsonRequest('GET', '/api/categories');

        $this->assertJsonStatus(200);
        self::assertIsArray($data['data'] ?? null);
        self::assertNotEmpty($data['data'] ?? []);
    }

    public function test_categories_accessible_without_auth(): void
    {
        $this->jsonRequest('GET', '/api/categories');
        $this->assertJsonStatus(200);
    }
}

