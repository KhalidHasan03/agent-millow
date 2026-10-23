<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

final class MessagePolicy
{
    public function view(User $actor, Message $message): bool
    {
        return $actor->is($message->conversation->user);
    }

    public function create(User $actor, Message $message): bool
    {
        return $actor->is($message->conversation->user);
    }

    public function delete(User $actor, Message $message): bool
    {
        return $actor->is($message->conversation->user);
    }
}
