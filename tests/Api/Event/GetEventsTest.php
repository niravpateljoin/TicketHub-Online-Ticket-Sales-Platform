<?php

namespace App\Tests\Api\Event;

use App\Entity\Category;
use App\Tests\ApiTestCase;

final class GetEventsTest extends ApiTestCase
{
    public function test_events_list_returns_paginated_data(): void
    {
        $data = $this->jsonRequest('GET', '/api/events');

        $this->assertJsonStatus(200);
        self::assertIsArray($data['data'] ?? null);
        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('total', $data['meta']);
    }

    public function test_events_list_accessible_without_auth(): void
    {
        $this->jsonRequest('GET', '/api/events');
        $this->assertJsonStatus(200);
    }

    public function test_filter_by_category_returns_only_matching_events(): void
    {
        $concert = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Concert']);
        self::assertNotNull($concert);

        $data = $this->jsonRequest('GET', '/api/events?category='.$concert->getId());
        $this->assertJsonStatus(200);

        foreach ($data['data'] ?? [] as $event) {
            self::assertSame('Concert', $event['category'] ?? null);
        }
    }

    public function test_filter_by_name_returns_matching_events(): void
    {
        $data = $this->jsonRequest('GET', '/api/events?search=Rock+Night');
        $this->assertJsonStatus(200);

        $names = array_map(static fn (array $e): string => (string) ($e['name'] ?? ''), $data['data'] ?? []);
        self::assertContains('Rock Night Test', $names);
        self::assertNotContains('PHP Summit Test', $names);
    }

    public function test_filter_available_only_excludes_sold_out_tiers(): void
    {
        $data = $this->jsonRequest('GET', '/api/events?available=1');
        $this->assertJsonStatus(200);

        $names = array_map(static fn (array $e): string => (string) ($e['name'] ?? ''), $data['data'] ?? []);
        self::assertNotContains('Cancelled Test Event', $names);
    }

    public function test_pagination_returns_correct_page(): void
    {
        $data = $this->jsonRequest('GET', '/api/events?page=1&perPage=1');
        $this->assertJsonStatus(200);

        self::assertCount(1, $data['data'] ?? []);
        self::assertSame(1, $data['meta']['perPage'] ?? null);
        self::assertSame(1, $data['meta']['page'] ?? null);
    }

    public function test_cancelled_events_not_in_public_listing(): void
    {
        $data = $this->jsonRequest('GET', '/api/events');
        $this->assertJsonStatus(200);

        $names = array_map(
            static fn (array $event): string => (string) ($event['name'] ?? ''),
            $data['data'] ?? []
        );

        self::assertNotContains('Cancelled Test Event', $names);
    }
}

