<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;

final class MemoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Memory $memory): bool
    {
        return $actor->is($memory->user);
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, Memory $memory): bool
    {
        return $actor->is($memory->user);
    }

    public function delete(User $actor, Memory $memory): bool
    {
        return $actor->is($memory->user);
    }
}
