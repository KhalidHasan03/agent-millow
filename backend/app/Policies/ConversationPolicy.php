<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Conversation $conversation): bool
    {
        return $actor->is($conversation->user);
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, Conversation $conversation): bool
    {
        return $actor->is($conversation->user);
    }

    public function delete(User $actor, Conversation $conversation): bool
    {
        return $actor->is($conversation->user);
    }
}
