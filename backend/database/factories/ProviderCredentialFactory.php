<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProviderCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderCredential>
 */
class ProviderCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'fake',
            'label' => 'default',
            'secret' => 'sk-'.fake()->sha256(),
            'settings' => null,
            'is_active' => true,
            'last_used_at' => null,
        ];
    }
}
