<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProviderUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderUsage>
 */
class ProviderUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prompt = fake()->numberBetween(10, 500);
        $completion = fake()->numberBetween(10, 500);

        return [
            'user_id' => null,
            'conversation_id' => null,
            'message_id' => null,
            'provider' => 'fake',
            'model' => 'fake-model',
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'latency_ms' => fake()->numberBetween(20, 2000),
            'status' => 'success',
            'error_code' => null,
            'estimated_cost' => null,
            'request_id' => null,
        ];
    }
}
