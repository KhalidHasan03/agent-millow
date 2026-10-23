<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SessionService
{
    public function logoutEverywhere(User $user, ?string $currentSessionId = null): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }
}
