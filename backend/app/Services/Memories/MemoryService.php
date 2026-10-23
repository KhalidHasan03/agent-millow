<?php

declare(strict_types=1);

namespace App\Services\Memories;

use App\DataTransferObjects\Memories\CreateMemoryData;
use App\Enums\MemoryCategory;
use App\Enums\MemorySource;
use App\Models\Memory;
use App\Models\MemoryEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MemoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Memory>
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 30);
        $perPage = max(1, min($perPage, 100));

        return Memory::query()
            ->where('user_id', $user->getKey())
            ->when(isset($filters['category']) && is_string($filters['category']), fn ($query) => $query->where('category', $filters['category']))
            ->when(isset($filters['source']) && is_string($filters['source']), fn ($query) => $query->where('source', $filters['source']))
            ->when(isset($filters['pinned']), fn ($query) => $query->where('pinned', (bool) $filters['pinned']))
            ->when(isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '', fn ($query) => $query->where(function ($sub) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $sub->where('title', 'like', $term)->orWhere('content', 'like', $term);
            }))
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(User $user, CreateMemoryData $data, ?Request $request = null): Memory
    {
        return DB::transaction(function () use ($user, $data, $request): Memory {
            $memory = Memory::query()->create([
                'user_id' => $user->getKey(),
                'key' => $data->key,
                'title' => $data->title,
                'content' => $data->content,
                'category' => $data->category !== null ? MemoryCategory::tryFrom($data->category) ?? MemoryCategory::Fact : MemoryCategory::Fact,
                'source' => $data->source !== null ? MemorySource::tryFrom($data->source) ?? MemorySource::User : MemorySource::User,
                'confidence' => $data->confidence ?? 100,
                'sensitive' => $data->sensitive,
                'pinned' => $data->pinned,
            ]);

            $this->recordEvent($user, $memory, 'created', $request);

            return $memory;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Memory $memory, array $attributes, User $actor, ?Request $request = null): Memory
    {
        return DB::transaction(function () use ($memory, $attributes, $actor, $request): Memory {
            $payload = [];

            if (array_key_exists('title', $attributes)) {
                $payload['title'] = (string) $attributes['title'];
            }

            if (array_key_exists('content', $attributes)) {
                $payload['content'] = (string) $attributes['content'];
            }

            if (array_key_exists('key', $attributes)) {
                $payload['key'] = $attributes['key'] === null ? null : (string) $attributes['key'];
            }

            if (array_key_exists('category', $attributes) && is_string($attributes['category'])) {
                $payload['category'] = MemoryCategory::tryFrom($attributes['category']) ?? $memory->category;
            }

            if (array_key_exists('confidence', $attributes)) {
                $payload['confidence'] = (int) $attributes['confidence'];
            }

            if (array_key_exists('sensitive', $attributes)) {
                $payload['sensitive'] = (bool) $attributes['sensitive'];
            }

            if (array_key_exists('pinned', $attributes)) {
                $payload['pinned'] = (bool) $attributes['pinned'];
            }

            $memory->fill($payload)->save();

            $this->recordEvent($actor, $memory, 'updated', $request);

            return $memory->refresh();
        });
    }

    public function inspect(Memory $memory, User $actor, ?Request $request = null): Memory
    {
        $memory->forceFill(['last_accessed_at' => now()])->save();

        $this->recordEvent($actor, $memory, 'inspected', $request);

        return $memory->refresh();
    }

    public function delete(Memory $memory, User $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($memory, $actor, $request): void {
            $this->recordEvent($actor, $memory, 'deleted', $request);
            $memory->delete();
        });
    }

    private function recordEvent(User $actor, Memory $memory, string $action, ?Request $request): void
    {
        $request ??= request();

        MemoryEvent::query()->create([
            'user_id' => $actor->getKey(),
            'memory_id' => $memory->getKey(),
            'action' => $action,
            'actor_type' => $actor->getKey() === $memory->user_id ? 'user' : 'user',
            'request_id' => $this->requestId($request),
            'ip_address' => $request?->ip(),
            'context' => null,
        ]);
    }

    private function requestId(?Request $request): ?string
    {
        $value = $request?->attributes->get('request_id');

        return is_string($value) ? $value : null;
    }
}
