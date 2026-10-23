<?php

declare(strict_types=1);

namespace App\Providers\Llm\Exceptions;

/**
 * Thrown when a provider rejects the supplied credentials.
 */
final class ProviderAuthenticationException extends ProviderException {}
