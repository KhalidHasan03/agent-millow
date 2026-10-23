<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Validation\ValidationException;

final class BetaAccessPolicy
{
    /**
     * @return array<int, string>
     */
    public function allowedEmails(): array
    {
        $emails = config('agent_millow.private_beta.allowed_emails', []);

        if (! is_array($emails)) {
            return [];
        }

        return array_values(array_filter($emails, 'is_string'));
    }

    public function enabled(): bool
    {
        return (bool) config('agent_millow.private_beta.enabled', false);
    }

    public function registrationEnabled(): bool
    {
        return (bool) config('agent_millow.registration.enabled', true);
    }

    public function isAllowed(string $email): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $normalized = mb_strtolower(trim($email));

        return in_array($normalized, $this->allowedEmails(), true);
    }

    /**
     * @throws ValidationException
     */
    public function assertMayAuthenticate(string $email): void
    {
        if ($this->isAllowed($email)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ['These credentials do not match our records.'],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function assertRegistrationEnabled(): void
    {
        if ($this->registrationEnabled()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ['Registration is currently disabled.'],
        ]);
    }
}
