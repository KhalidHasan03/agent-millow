<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'display_name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'enabled' => true,
            'requires_network' => false,
        ];
    }
}
