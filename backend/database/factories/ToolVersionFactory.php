<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tool;
use App\Models\ToolVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolVersion>
 */
class ToolVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_id' => Tool::factory(),
            'version' => 1,
            'input_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [],
            ],
            'output_schema' => null,
            'risk_level' => 'low',
            'required_scopes' => [],
            'timeout_seconds' => 30,
            'approval_required' => false,
            'limits' => [],
            'metadata' => null,
            'enabled' => true,
        ];
    }

    public function highRisk(): static
    {
        return $this->state(fn (array $attributes): array => [
            'risk_level' => 'high',
            'approval_required' => true,
        ]);
    }
}
