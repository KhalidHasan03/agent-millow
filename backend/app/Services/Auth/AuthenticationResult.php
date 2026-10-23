<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

final readonly class AuthenticationResult
{
    public function __construct(
        public User $user,
        public ?string $plainTextToken,
    ) {}

    public function issuedToken(): bool
    {
        return $this->plainTextToken !== null;
    }
}
