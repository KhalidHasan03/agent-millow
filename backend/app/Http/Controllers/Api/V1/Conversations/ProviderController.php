<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Http\Controllers\Controller;
use App\Providers\Llm\ProviderRegistry;
use App\Providers\Llm\ProviderStatus;
use Illuminate\Http\JsonResponse;

final class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (ProviderStatus $status): array => $status->toArray(),
                $this->providers->statuses(),
            ),
            'meta' => [
                'default' => (string) config('agent_millow.providers.default', 'fake'),
            ],
        ]);
    }
}
