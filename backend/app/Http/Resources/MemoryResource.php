<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Memory $resource
 */
final class MemoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $memory = $this->resource;

        return [
            'id' => $memory->id,
            'key' => $memory->key,
            'title' => $memory->title,
            'content' => $memory->content,
            'category' => $memory->category->value,
            'source' => $memory->source->value,
            'confidence' => $memory->confidence,
            'sensitive' => $memory->sensitive,
            'pinned' => $memory->pinned,
            'last_accessed_at' => $memory->last_accessed_at?->toIso8601String(),
            'created_at' => $memory->created_at?->toIso8601String(),
            'updated_at' => $memory->updated_at?->toIso8601String(),
        ];
    }
}
