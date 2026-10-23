<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('AGENT_MILLOW_SEED_ADMIN_EMAIL', '');
        $password = (string) env('AGENT_MILLOW_SEED_ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->command?->warn('Skipping admin seed: set AGENT_MILLOW_SEED_ADMIN_EMAIL and AGENT_MILLOW_SEED_ADMIN_PASSWORD.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ],
        );

        $this->command?->info('Admin user seeded.');
    }
}
