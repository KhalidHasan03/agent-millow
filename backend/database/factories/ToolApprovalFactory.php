<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ToolApproval;
use App\Models\ToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolApproval>
 */
class ToolApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_call_id' => ToolCall::factory(),
            'user_id' => fn (array $attributes): int => (int) ToolCall::query()
                ->whereKey($attributes['tool_call_id'])
                ->value('user_id'),
            'status' => 'pending',
            'reason' => null,
            'decided_at' => null,
        ];
    }
}
