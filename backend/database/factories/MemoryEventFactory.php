<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Memory;
use App\Models\MemoryEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryEvent>
 */
class MemoryEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'memory_id' => Memory::factory(),
            'action' => 'created',
            'actor_type' => 'user',
            'request_id' => null,
            'ip_address' => fake()->ipv4(),
            'context' => null,
        ];
    }
}
