<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

final readonly class RegistrationData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromRequest(array $validated, string $deviceName): self
    {
        return new self(
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            password: (string) $validated['password'],
            deviceName: $deviceName,
        );
    }
}
