<?php

declare(strict_types=1);

namespace App\Providers\Llm\Exceptions;

use RuntimeException;

/**
 * Base class for provider failures. Messages must be safe to surface to the
 * user and must never contain credentials or raw upstream bodies.
 */
class ProviderException extends RuntimeException {}
