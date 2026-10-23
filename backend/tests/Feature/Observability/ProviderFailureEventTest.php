<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Jobs\GenerateAssistantReply;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Providers\Llm\Contracts\ProviderAdapter;
use App\Providers\Llm\Contracts\StreamEvent;
use App\Providers\Llm\Exceptions\ProviderException;
use App\Providers\Llm\ProviderRegistry;
use App\Services\Conversations\MessageService;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class ProviderFailureEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_reply_failure_is_logged_without_exposing_the_secret(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create([
            'provider' => 'failing',
            'model' => 'test-model',
        ]);
        $userMessage = Message::factory()->for($conversation)->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $this->useFailingProvider();

        Log::spy();

        try {
            (new GenerateAssistantReply($conversation->id, $user->id, $userMessage->id))
                ->handle(app(MessageService::class));

            $this->fail('The provider exception was not propagated.');
        } catch (ProviderException) {
            // Expected.
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($conversation, $user): bool {
                return $message === 'provider.reply_failed'
                    && $context['provider'] === 'failing'
                    && $context['conversation_id'] === $conversation->id
                    && $context['user_id'] === $user->id;
            });
    }

    public function test_stream_failure_is_logged_and_returns_a_generic_error(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create([
            'provider' => 'failing',
            'model' => 'test-model',
        ]);

        $this->useFailingProvider();

        Log::spy();

        $response = $this->actingAs($user)
            ->get('/api/v1/conversations/'.$conversation->id.'/stream');

        $content = $response->streamedContent();

        $this->assertStringContainsString('event: error', $content);
        $this->assertStringContainsString('The provider request failed.', $content);
        $this->assertStringNotContainsString('sk-super-secret', $content);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($conversation, $user): bool {
                return $message === 'provider.stream_failed'
                    && $context['provider'] === 'failing'
                    && $context['conversation_id'] === $conversation->id
                    && $context['user_id'] === $user->id;
            });
    }

    private function useFailingProvider(): void
    {
        $provider = Mockery::mock(ProviderAdapter::class);
        $provider->shouldReceive('name')->andReturn('failing');
        $provider->shouldReceive('supportsStreaming')->andReturn(true);
        $provider->shouldReceive('isConfigured')->andReturn(true);
        $provider->shouldReceive('complete')->andThrow(new ProviderException('Bearer sk-super-secret'));
        $provider->shouldReceive('stream')->andReturnUsing(function (): Generator {
            throw new ProviderException('Bearer sk-super-secret');
            yield StreamEvent::done();
        });

        $this->app->instance(ProviderRegistry::class, new ProviderRegistry([$provider]));
        $this->app->forgetInstance(MessageService::class);
    }
}
