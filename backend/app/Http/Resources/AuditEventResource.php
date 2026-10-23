<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuditEvent $resource
 */
final class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->id,
            'event' => $event->event,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'request_id' => $event->request_id,
            'ip_address' => $event->ip_address,
            'context' => $event->context,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }
}
