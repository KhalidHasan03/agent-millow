<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Monolog\LogRecord;
use Throwable;

/**
 * Adds safe HTTP request context to every log record.
 *
 * Only identifiers are added: no query strings, headers, bodies, emails, or
 * tokens. Console and queue logs are left untouched unless an explicit
 * request_id attribute is present.
 */
final class RequestContext
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (! app()->bound('request')) {
            return $record;
        }

        $request = request();

        if (! $request instanceof Request) {
            return $record;
        }

        $requestId = $request->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            return $record;
        }

        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
        ];

        $route = $request->route();

        if ($route instanceof Route && is_string($route->getName())) {
            $context['route'] = $route->getName();
        }

        $userId = $this->userId($request);

        if ($userId !== null) {
            $context['user_id'] = $userId;
        }

        return $record->with(context: array_merge($context, $record->context));
    }

    private function userId(Request $request): int|string|null
    {
        try {
            $identifier = $request->user()?->getAuthIdentifier();
        } catch (Throwable) {
            return null;
        }

        return is_int($identifier) || is_string($identifier) ? $identifier : null;
    }
}
