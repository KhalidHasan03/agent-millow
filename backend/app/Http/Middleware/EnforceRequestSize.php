<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceRequestSize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $maxBytes = (int) config('agent_millow.security.request_max_bytes', 1048576);

        if ($maxBytes <= 0) {
            return $next($request);
        }

        $declared = $request->headers->get('Content-Length');
        $declaredBytes = is_string($declared) && ctype_digit($declared) ? (int) $declared : 0;

        if ($declaredBytes > $maxBytes) {
            return $this->tooLarge($request);
        }

        if (strlen($request->getContent()) > $maxBytes) {
            return $this->tooLarge($request);
        }

        return $next($request);
    }

    private function tooLarge(Request $request): Response
    {
        return response()->json([
            'message' => 'Request payload is too large.',
            'request_id' => $request->attributes->get('request_id'),
        ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }
}
