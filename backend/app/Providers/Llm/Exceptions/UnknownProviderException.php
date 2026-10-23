<?php

declare(strict_types=1);

namespace App\Providers\Llm\Exceptions;

/**
 * Thrown when a requested provider is not registered or is disabled.
 */
final class UnknownProviderException extends ProviderException {}
