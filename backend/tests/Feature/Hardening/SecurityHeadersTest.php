<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_hsts_is_only_sent_for_secure_requests_when_enabled(): void
    {
        config()->set('agent_millow.security.hsts.enabled', true);
        config()->set('agent_millow.security.hsts.max_age', 31536000);

        $response = $this->get('https://localhost/up');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_not_sent_when_disabled(): void
    {
        config()->set('agent_millow.security.hsts.enabled', false);

        $this->get('https://localhost/up')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
