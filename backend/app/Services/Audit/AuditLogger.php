<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $event,
        ?User $actor = null,
        ?Model $subject = null,
        array $context = [],
        ?Request $request = null,
    ): AuditEvent {
        $request ??= request();

        return AuditEvent::query()->create([
            'user_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'request_id' => $this->requestId($request),
            'ip_address' => $request?->ip(),
            'context' => $context === [] ? null : $this->redact($context),
        ]);
    }

    private function requestId(?Request $request): ?string
    {
        $value = $request?->attributes->get('request_id');

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $blocked = ['password', 'password_confirmation', 'current_password', 'token', 'secret', 'api_key'];

        foreach ($blocked as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
