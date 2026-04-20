<?php

namespace App\Tests\Api\Admin;

use App\Entity\Organizer;
use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class OrganizerManagementTest extends ApiTestCase
{
    public function test_admin_can_approve_pending_organizer(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $organizer = $this->em->getRepository(Organizer::class)
            ->findOneBy(['approvalStatus' => Organizer::STATUS_PENDING]);
        self::assertNotNull($organizer);

        $data = $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/approve', token: $token);
        $this->assertJsonStatus(200);
        self::assertSame(Organizer::STATUS_APPROVED, $data['data']['approvalStatus'] ?? null);
    }

    public function test_admin_can_reject_pending_organizer(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $organizer = $this->em->getRepository(Organizer::class)
            ->findOneBy(['approvalStatus' => Organizer::STATUS_PENDING]);
        self::assertNotNull($organizer);

        $data = $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/reject', token: $token);
        $this->assertJsonStatus(200);
        self::assertSame(Organizer::STATUS_REJECTED, $data['data']['approvalStatus'] ?? null);
    }

    public function test_admin_can_deactivate_organizer(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $organizer = $this->em->getRepository(Organizer::class)
            ->findOneBy(['approvalStatus' => Organizer::STATUS_APPROVED]);
        self::assertNotNull($organizer);

        $data = $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/deactivate', token: $token);
        $this->assertJsonStatus(200);
        self::assertNotNull($data['data']['deactivatedAt'] ?? null);
    }

    public function test_admin_can_reactivate_organizer(): void
    {
        $token = $this->loginAs(TestFixtures::ADMIN_EMAIL, 'password123');
        $organizer = $this->em->getRepository(Organizer::class)
            ->findOneBy(['approvalStatus' => Organizer::STATUS_APPROVED]);
        self::assertNotNull($organizer);

        $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/deactivate', token: $token);
        $this->assertJsonStatus(200);

        $data = $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/reactivate', token: $token);
        $this->assertJsonStatus(200);
        self::assertSame(Organizer::STATUS_APPROVED, $data['data']['approvalStatus'] ?? null);
        self::assertNull($data['data']['deactivatedAt'] ?? 'not-null');
    }

    public function test_non_admin_cannot_access_organizer_management(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');
        $organizer = $this->em->getRepository(Organizer::class)
            ->findOneBy(['approvalStatus' => Organizer::STATUS_PENDING]);
        self::assertNotNull($organizer);

        $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/approve', token: $token);
        $this->assertJsonStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_routes(): void
    {
        $organizer = $this->em->getRepository(Organizer::class)
            ->findOneBy(['approvalStatus' => Organizer::STATUS_PENDING]);
        self::assertNotNull($organizer);

        $this->jsonRequest('POST', '/api/admin/organizers/'.$organizer->getId().'/approve');
        $this->assertJsonStatus(401);
    }
}

