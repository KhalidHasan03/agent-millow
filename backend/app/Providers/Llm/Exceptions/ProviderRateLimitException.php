<?php

declare(strict_types=1);

namespace App\Providers\Llm\Exceptions;

/**
 * Thrown when a provider rate limits the request.
 */
final class ProviderRateLimitException extends ProviderException {}
