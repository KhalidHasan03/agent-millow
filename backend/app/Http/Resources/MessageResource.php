<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Message $resource
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'role' => $message->role->value,
            'content' => $message->content,
            'status' => $message->status->value,
            'provider' => $message->provider,
            'model' => $message->model,
            'finish_reason' => $message->finish_reason,
            'prompt_tokens' => $message->prompt_tokens,
            'completion_tokens' => $message->completion_tokens,
            'client_message_id' => $message->client_message_id,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
