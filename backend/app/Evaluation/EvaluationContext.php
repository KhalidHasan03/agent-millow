<?php

declare(strict_types=1);

namespace App\Evaluation;

use App\Models\User;

final readonly class EvaluationContext
{
    public function __construct(
        public User $owner,
        public User $other,
        public User $admin,
    ) {}
}
