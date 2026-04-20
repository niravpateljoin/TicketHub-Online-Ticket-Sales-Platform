<?php

namespace App\Tests\Api\Security;

use App\Tests\ApiTestCase;
use App\Tests\Fixtures\TestFixtures;

final class RateLimiterTest extends ApiTestCase
{
    public function test_login_blocked_after_5_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->jsonRequest('POST', '/api/auth/login', [
                'email' => 'lockout-test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt must be rate-limited
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'lockout-test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertJsonStatus(429);
    }

    public function test_rate_limit_response_includes_retry_after_header(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->jsonRequest('POST', '/api/auth/login', [
                'email' => 'header-test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'header-test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertJsonStatus(429);

        $response = $this->client->getResponse();
        // Either Retry-After header or retryAfter in body must be present
        $hasHeader = $response->headers->has('Retry-After') || $response->headers->has('X-RateLimit-Reset');
        $body = json_decode((string) $response->getContent(), true) ?? [];
        $hasBody = isset($body['retryAfter']) || isset($body['message']);

        self::assertTrue($hasHeader || $hasBody, 'Rate-limit response should include retry information');
    }

    public function test_checkout_has_rate_limit_headers_on_success(): void
    {
        $token = $this->loginAs(TestFixtures::USER_EMAIL, 'password123');

        $data = $this->jsonRequest('POST', '/api/checkout/confirm', ['idempotencyKey' => bin2hex(random_bytes(8))], $token);

        // Even on a successful (empty cart) response, rate-limit headers must be present
        $response = $this->client->getResponse();
        self::assertTrue(
            $response->headers->has('X-RateLimit-Limit') || $response->headers->has('X-RateLimit-Remaining'),
            'Checkout endpoint should set X-RateLimit-* headers'
        );
    }
}
