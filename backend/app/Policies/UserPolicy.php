<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(UserRole::Admin);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->hasRole(UserRole::Admin);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->is($target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->is($target);
    }
}
