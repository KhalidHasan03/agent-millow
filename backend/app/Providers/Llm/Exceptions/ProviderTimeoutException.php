<?php

declare(strict_types=1);

namespace App\Providers\Llm\Exceptions;

/**
 * Thrown when a provider does not respond within the configured timeout.
 */
final class ProviderTimeoutException extends ProviderException {}
