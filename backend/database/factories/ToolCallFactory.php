<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Tool;
use App\Models\ToolCall;
use App\Models\ToolVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ToolCall>
 */
class ToolCallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_id' => null,
            'tool_id' => Tool::factory(),
            'tool_version_id' => ToolVersion::factory(),
            'request_id' => Str::uuid()->toString(),
            'status' => 'pending',
            'risk_level' => 'low',
            'input' => [],
            'output' => null,
            'redacted_input' => [],
            'redacted_output' => null,
            'duration_ms' => null,
            'error_message' => null,
            'approved_at' => null,
            'executed_at' => null,
            'completed_at' => null,
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state(fn (array $attributes): array => [
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
        ]);
    }
}
