<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RequestSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_api_requests_are_rejected_with_json(): void
    {
        config()->set('agent_millow.security.request_max_bytes', 100);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => str_repeat('x', 300),
        ])
            ->assertStatus(413)
            ->assertJsonStructure(['message', 'request_id'])
            ->assertJsonPath('message', 'Request payload is too large.');
    }

    public function test_requests_within_the_limit_reach_the_application(): void
    {
        config()->set('agent_millow.security.request_max_bytes', 100000);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'secret123',
        ])->assertStatus(422);
    }
}
