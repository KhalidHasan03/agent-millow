<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Providers\Llm\Adapters\FakeProvider;
use App\Providers\Llm\Adapters\OpenAiCompatibleProvider;
use App\Providers\Llm\Exceptions\ProviderNotConfiguredException;
use App\Providers\Llm\ProviderRegistry;
use Tests\TestCase;

final class ProviderReadinessTest extends TestCase
{
    public function test_the_fake_provider_is_ready_without_credentials(): void
    {
        $registry = new ProviderRegistry([new FakeProvider]);

        $this->assertTrue($registry->isReady('fake'));
        $this->assertSame(['fake'], $registry->readyNames());
        $this->assertSame(
            [
                'name' => 'fake',
                'enabled' => true,
                'ready' => true,
                'supports_streaming' => true,
            ],
            $registry->statuses()[0]->toArray(),
        );
    }

    public function test_openai_is_not_ready_without_a_server_side_key(): void
    {
        config([
            'agent_millow.providers.default' => 'openai',
            'agent_millow.providers.openai.key' => '',
            'agent_millow.providers.openai.base_url' => 'https://api.openai.com/v1',
            'agent_millow.providers.openai.default_model' => 'gpt-4o-mini',
            'agent_millow.providers.openai.allowed_models' => ['gpt-4o-mini'],
        ]);

        $registry = new ProviderRegistry([new OpenAiCompatibleProvider]);

        $this->assertFalse($registry->isReady('openai'));
        $this->assertSame([], $registry->readyNames());
        $this->assertFalse($registry->defaultIsReady());
        $this->assertSame('openai', $registry->defaultStatus()->name);
    }

    public function test_openai_is_ready_with_a_key_and_an_allowed_model(): void
    {
        config([
            'agent_millow.providers.default' => 'openai',
            'agent_millow.providers.openai.key' => 'local-test-provider-key',
            'agent_millow.providers.openai.base_url' => 'https://api.openai.com/v1',
            'agent_millow.providers.openai.default_model' => 'gpt-4o-mini',
            'agent_millow.providers.openai.allowed_models' => ['gpt-4o-mini'],
        ]);

        $registry = new ProviderRegistry([new OpenAiCompatibleProvider]);

        $this->assertTrue($registry->isReady('openai'));
        $this->assertSame(['openai'], $registry->readyNames());
        $this->assertTrue($registry->defaultIsReady());
    }

    public function test_openai_is_not_ready_when_its_model_is_not_allowed(): void
    {
        config([
            'agent_millow.providers.openai.key' => 'local-test-provider-key',
            'agent_millow.providers.openai.base_url' => 'https://api.openai.com/v1',
            'agent_millow.providers.openai.default_model' => 'gpt-4o',
            'agent_millow.providers.openai.allowed_models' => ['gpt-4o-mini'],
        ]);

        $registry = new ProviderRegistry([new OpenAiCompatibleProvider]);

        $this->assertFalse($registry->isReady('openai'));
    }

    public function test_openai_is_not_ready_with_an_invalid_base_url(): void
    {
        config([
            'agent_millow.providers.openai.key' => 'local-test-provider-key',
            'agent_millow.providers.openai.base_url' => 'not-a-url',
            'agent_millow.providers.openai.default_model' => 'gpt-4o-mini',
            'agent_millow.providers.openai.allowed_models' => ['gpt-4o-mini'],
        ]);

        $registry = new ProviderRegistry([new OpenAiCompatibleProvider]);

        $this->assertFalse($registry->isReady('openai'));
    }

    public function test_get_ready_rejects_an_unconfigured_provider(): void
    {
        config([
            'agent_millow.providers.openai.key' => '',
            'agent_millow.providers.openai.base_url' => 'https://api.openai.com/v1',
            'agent_millow.providers.openai.default_model' => 'gpt-4o-mini',
            'agent_millow.providers.openai.allowed_models' => ['gpt-4o-mini'],
        ]);

        $registry = new ProviderRegistry([new OpenAiCompatibleProvider]);

        $this->expectException(ProviderNotConfiguredException::class);
        $this->expectExceptionMessage('Provider [openai] is not configured.');

        $registry->getReady('openai');
    }

    public function test_the_default_status_reports_a_default_that_is_not_registered(): void
    {
        config(['agent_millow.providers.default' => 'missing-provider']);

        $registry = new ProviderRegistry([new FakeProvider]);
        $status = $registry->defaultStatus();

        $this->assertSame('missing-provider', $status->name);
        $this->assertFalse($status->enabled);
        $this->assertFalse($status->ready);
        $this->assertFalse($status->supportsStreaming);
    }
}
