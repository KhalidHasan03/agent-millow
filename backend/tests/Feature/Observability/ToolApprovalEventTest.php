<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Models\Tool;
use App\Models\User;
use App\Services\Tools\Registry\ToolRegistry;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ToolApprovalEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ToolSeeder::class);

        $tool = Tool::query()->where('name', 'echo')->firstOrFail();
        $tool->versions()->update(['risk_level' => 'high', 'approval_required' => true]);

        app(ToolRegistry::class)->clearCache();
    }

    public function test_tool_approval_is_logged(): void
    {
        $user = User::factory()->create();

        $call = $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo',
                'input' => ['message' => 'approve me'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending_approval');

        $callId = $call->json('tool_call_id');

        Log::spy();

        $this->actingAs($user)
            ->postJson('/api/v1/tools/calls/'.$callId.'/approve')
            ->assertOk()
            ->assertJsonPath('status', 'succeeded');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($callId, $user): bool {
                return $message === 'tool.approved'
                    && $context['tool'] === 'echo'
                    && $context['tool_call_id'] === $callId
                    && $context['user_id'] === $user->id
                    && $context['risk_level'] === 'high';
            });
    }

    public function test_tool_rejection_is_logged(): void
    {
        $user = User::factory()->create();

        $call = $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo',
                'input' => ['message' => 'reject me'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending_approval');

        $callId = $call->json('tool_call_id');

        Log::spy();

        $this->actingAs($user)
            ->postJson('/api/v1/tools/calls/'.$callId.'/reject', ['reason' => 'not needed'])
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($callId, $user): bool {
                return $message === 'tool.rejected'
                    && $context['tool'] === 'echo'
                    && $context['tool_call_id'] === $callId
                    && $context['user_id'] === $user->id
                    && $context['risk_level'] === 'high';
            });
    }
}
