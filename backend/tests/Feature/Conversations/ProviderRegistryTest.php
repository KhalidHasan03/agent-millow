<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Providers\Llm\Exceptions\UnknownProviderException;
use App\Providers\Llm\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registry_resolves_the_default_fake_provider(): void
    {
        $registry = app(ProviderRegistry::class);

        $this->assertTrue($registry->has('fake'));
        $this->assertSame('fake', $registry->default()->name());
        $this->assertContains('fake', $registry->names());
    }

    public function test_an_unknown_provider_throws(): void
    {
        $this->expectException(UnknownProviderException::class);

        app(ProviderRegistry::class)->get('nope');
    }

    public function test_conversation_policy_isolates_users(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = Conversation::factory()->for($alice)->create();

        $this->assertTrue($alice->can('view', $conversation));
        $this->assertFalse($bob->can('view', $conversation));
        $this->assertFalse($bob->can('update', $conversation));
        $this->assertFalse($bob->can('delete', $conversation));
    }

    public function test_message_policy_uses_the_parent_conversation_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = Conversation::factory()->for($alice)->create();
        $message = Message::factory()->for($conversation)->create();

        $this->assertTrue($alice->can('view', $message));
        $this->assertFalse($bob->can('view', $message));
        $this->assertFalse($bob->can('delete', $message));
    }
}
