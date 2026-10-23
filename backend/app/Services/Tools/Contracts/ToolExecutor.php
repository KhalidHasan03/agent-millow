<?php

declare(strict_types=1);

namespace App\Services\Tools\Contracts;

use App\Models\User;

interface ToolExecutor
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(User $user, array $input): array;
}
