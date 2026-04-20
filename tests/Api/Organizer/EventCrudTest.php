<?php

namespace App\Tests\Api\Organizer;

use App\Entity\Category;
use App\Entity\Event;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class EventCrudTest extends ApiTestCase
{
    private function eventPayload(string $name, string $categoryId): array
    {
        return [
            'name'         => $name,
            'category'     => $categoryId,
            'startDate'    => (new \DateTime('+30 days'))->format('Y-m-d'),
            'startTime'    => '18:00',
            'venueName'    => 'Test Venue',
            'venueAddress' => '123 Test Street',
            'isOnline'     => false,
        ];
    }

    public function test_approved_organizer_can_create_event(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Concert']);

        $data = $this->jsonRequest('POST', '/api/organizer/events', $this->eventPayload('My New Event', (string) $category->getId()), $token);

        $this->assertJsonStatus(201);
        self::assertSame('My New Event', $data['data']['name'] ?? null);

        $stored = $this->em->getRepository(Event::class)->findOneBy(['name' => 'My New Event']);
        self::assertNotNull($stored);
    }

    public function test_pending_organizer_cannot_create_event(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_PENDING_EMAIL, 'password123');
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Concert']);

        $this->jsonRequest('POST', '/api/organizer/events', $this->eventPayload('Blocked Event', (string) $category->getId()), $token);

        $this->assertJsonStatus(403);
    }

    public function test_organizer_can_edit_own_event(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Concert']);

        $payload = $this->eventPayload('Rock Night Updated', (string) $category->getId());
        $data = $this->jsonRequest('PUT', '/api/organizer/events/'.$event->getId(), $payload, $token);

        $this->assertJsonStatus(200);
        self::assertSame('Rock Night Updated', $data['data']['name'] ?? null);
    }

    public function test_organizer_cannot_edit_another_organizers_event(): void
    {
        // Register a second organizer and approve them
        $this->jsonRequest('POST', '/api/auth/register/organizer', [
            'email' => 'other-organizer@test.local',
            'password' => 'password123',
        ]);

        // Approve them via admin
        $adminToken = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $data = $this->jsonRequest('GET', '/api/admin/organizers?status=pending', token: $adminToken);
        $pendingId = null;
        foreach ($data['data'] ?? [] as $o) {
            if ($o['email'] === 'other-organizer@test.local') {
                $pendingId = $o['id'];
            }
        }
        self::assertNotNull($pendingId);
        $this->jsonRequest('POST', '/api/admin/organizers/'.$pendingId.'/approve', token: $adminToken);

        // Now the second organizer tries to edit the first organizer's event
        $token2 = $this->loginAs('other-organizer@test.local', 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Concert']);

        $this->jsonRequest('PUT', '/api/organizer/events/'.$event->getId(), $this->eventPayload('Stolen Event', (string) $category->getId()), $token2);

        $this->assertJsonStatus(403);
    }

    public function test_event_creation_fails_without_required_fields(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');

        $data = $this->jsonRequest('POST', '/api/organizer/events', ['description' => 'Missing name and category'], $token);

        $this->assertJsonStatus(422);
        self::assertArrayHasKey('errors', $data);
    }

    public function test_organizer_can_soft_cancel_own_event(): void
    {
        $token = $this->loginAs(TestFixtures::ORGANIZER_APPROVED_EMAIL, 'password123');
        $event = $this->em->getRepository(Event::class)->findOneBy(['slug' => 'rock-night-test']);

        $data = $this->jsonRequest('POST', '/api/organizer/events/'.$event->getId().'/cancel', token: $token);

        $this->assertJsonStatus(200);
        self::assertSame('cancelled', $data['data']['event']['status'] ?? null);
    }
}
