<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MemoryCategory;
use App\Enums\MemorySource;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => null,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'category' => MemoryCategory::Fact,
            'source' => MemorySource::User,
            'confidence' => 100,
            'sensitive' => false,
            'pinned' => false,
            'metadata' => null,
            'last_accessed_at' => null,
        ];
    }

    public function modelProposed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => MemorySource::Model,
            'confidence' => fake()->numberBetween(60, 95),
        ]);
    }

    public function sensitive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sensitive' => true,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pinned' => true,
        ]);
    }
}
