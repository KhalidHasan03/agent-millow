<?php

declare(strict_types=1);

namespace App\Services\Tools\Executors;

use App\Models\User;
use App\Services\Tools\Contracts\ToolExecutor;

final class EchoToolExecutor implements ToolExecutor
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(User $user, array $input): array
    {
        $message = isset($input['message']) && is_string($input['message']) ? $input['message'] : '';

        return [
            'echo' => $message,
            'user_id' => $user->id,
        ];
    }
}
