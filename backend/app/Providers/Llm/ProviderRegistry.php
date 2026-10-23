<?php

declare(strict_types=1);

namespace App\Providers\Llm;

use App\Providers\Llm\Contracts\ProviderAdapter;
use App\Providers\Llm\Exceptions\ProviderNotConfiguredException;
use App\Providers\Llm\Exceptions\UnknownProviderException;

/**
 * Resolves provider adapters by name from what the container has tagged.
 */
final class ProviderRegistry
{
    /**
     * @param  iterable<ProviderAdapter>  $adapters
     */
    public function __construct(private readonly iterable $adapters) {}

    public function get(string $name): ProviderAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->name() === $name) {
                return $adapter;
            }
        }

        throw new UnknownProviderException(sprintf('Unknown or disabled provider [%s].', $name));
    }

    public function has(string $name): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->name() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->adapters as $adapter) {
            $names[] = $adapter->name();
        }

        return $names;
    }

    /**
     * @return list<ProviderStatus>
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach ($this->adapters as $adapter) {
            $statuses[] = new ProviderStatus(
                name: $adapter->name(),
                enabled: true,
                ready: $adapter->isConfigured(),
                supportsStreaming: $adapter->supportsStreaming(),
            );
        }

        return $statuses;
    }

    /**
     * @return list<string>
     */
    public function readyNames(): array
    {
        $names = [];

        foreach ($this->statuses() as $status) {
            if ($status->ready) {
                $names[] = $status->name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public function allowedModels(string $name): array
    {
        $models = config("agent_millow.providers.{$name}.allowed_models", []);

        if (! is_array($models)) {
            return [];
        }

        $allowed = [];

        foreach ($models as $model) {
            if (! is_string($model)) {
                continue;
            }

            $model = trim($model);

            if ($model !== '' && ! in_array($model, $allowed, true)) {
                $allowed[] = $model;
            }
        }

        return $allowed;
    }

    public function supportsModel(string $name, string $model): bool
    {
        return in_array($model, $this->allowedModels($name), true);
    }

    public function isReady(string $name): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->name() === $name) {
                return $adapter->isConfigured();
            }
        }

        return false;
    }

    public function getReady(string $name): ProviderAdapter
    {
        $adapter = $this->get($name);

        if (! $adapter->isConfigured()) {
            throw new ProviderNotConfiguredException(sprintf('Provider [%s] is not configured.', $name));
        }

        return $adapter;
    }

    public function default(): ProviderAdapter
    {
        $name = (string) config('agent_millow.providers.default', 'fake');

        return $this->get($name);
    }

    public function defaultStatus(): ProviderStatus
    {
        $name = (string) config('agent_millow.providers.default', 'fake');

        foreach ($this->statuses() as $status) {
            if ($status->name === $name) {
                return $status;
            }
        }

        return new ProviderStatus(
            name: $name,
            enabled: false,
            ready: false,
            supportsStreaming: false,
        );
    }

    public function defaultIsReady(): bool
    {
        return $this->defaultStatus()->ready;
    }
}
