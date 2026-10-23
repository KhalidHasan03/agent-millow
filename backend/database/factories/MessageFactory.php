<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => null,
            'role' => MessageRole::User,
            'content' => fake()->paragraph(),
            'status' => MessageStatus::Complete,
            'completed_at' => now(),
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MessageRole::Assistant,
            'user_id' => null,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MessageRole::System,
        ]);
    }
}
