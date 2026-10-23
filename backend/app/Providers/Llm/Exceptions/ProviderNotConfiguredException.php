<?php

declare(strict_types=1);

namespace App\Providers\Llm\Exceptions;

/**
 * Thrown when a provider is enabled but lacks the server-side configuration
 * required to call it.
 */
final class ProviderNotConfiguredException extends ProviderException {}
