<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class LoginCredentials
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
        public bool $issueToken,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromRequest(array $validated, string $deviceName): self
    {
        return new self(
            email: (string) $validated['email'],
            password: (string) $validated['password'],
            deviceName: $deviceName,
            issueToken: (bool) ($validated['issue_token'] ?? false),
        );
    }
}
