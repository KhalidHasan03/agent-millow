<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\DataTransferObjects\Conversations\CreateConversationData;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Providers\Llm\Exceptions\ProviderNotConfiguredException;
use App\Providers\Llm\ProviderRegistry;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class ConversationService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly AuditLogger $audit,
    ) {}

    public function create(User $user, CreateConversationData $data): Conversation
    {
        $provider = $this->resolveProvider($data->provider);

        $model = $data->model ?? $this->defaultModelFor($provider);

        if ($model === null || $model === '' || ! $this->providers->supportsModel($provider, $model)) {
            throw ValidationException::withMessages([
                'model' => 'The selected model is not available for this provider.',
            ]);
        }

        $conversation = Conversation::query()->create([
            'user_id' => $user->getKey(),
            'title' => $data->title,
            'system_prompt' => $data->systemPrompt,
            'status' => ConversationStatus::Active,
            'provider' => $provider,
            'model' => $model,
        ]);

        $this->audit->record('conversation.created', $user, $conversation, [
            'provider' => $provider,
            'model' => $model,
        ]);

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        return Conversation::query()
            ->where('user_id', $user->getKey())
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Conversation $conversation, array $attributes, User $actor): Conversation
    {
        $conversation->fill($attributes)->save();

        $this->audit->record('conversation.updated', $actor, $conversation);

        return $conversation;
    }

    public function delete(Conversation $conversation, User $actor): void
    {
        $conversation->delete();

        $this->audit->record('conversation.deleted', $actor, $conversation);
    }

    private function resolveProvider(?string $provider): string
    {
        if ($provider !== null) {
            $this->providers->getReady($provider);

            return $provider;
        }

        $default = $this->providers->defaultStatus();

        if (! $default->enabled || ! $default->ready) {
            throw new ProviderNotConfiguredException('The default provider is not configured.');
        }

        return $default->name;
    }

    private function defaultModelFor(string $provider): ?string
    {
        $model = config("agent_millow.providers.{$provider}.default_model");

        return is_string($model) ? $model : null;
    }
}
