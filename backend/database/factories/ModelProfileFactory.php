<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModelProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelProfile>
 */
class ModelProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'name' => 'fake-model',
            'label' => 'Fake Model',
            'context_window' => 8192,
            'max_output_tokens' => 2048,
            'supports_streaming' => true,
            'supports_tools' => false,
            'is_default' => true,
            'is_active' => true,
            'settings' => null,
        ];
    }
}
