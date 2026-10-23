<?php

declare(strict_types=1);

namespace App\Services\Tools\Registry;

use App\Models\Tool;
use Illuminate\Support\Facades\Cache;

final class ToolRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $key = 'agent_millow.tools.registry.v1';

        return Cache::remember($key, 300, function (): array {
            $tools = Tool::query()
                ->with(['versions' => fn ($q) => $q->where('enabled', true)->orderByDesc('version')])
                ->where('enabled', true)
                ->get();

            $result = [];
            foreach ($tools as $tool) {
                $version = $tool->versions->first();
                if ($version === null) {
                    continue;
                }
                $result[$tool->name] = [
                    'tool_id' => $tool->id,
                    'version_id' => $version->id,
                    'display_name' => $tool->display_name,
                    'description' => $tool->description,
                    'input_schema' => $version->input_schema,
                    'output_schema' => $version->output_schema,
                    'risk_level' => $version->risk_level,
                    'required_scopes' => $version->required_scopes ?? [],
                    'timeout_seconds' => $version->timeout_seconds,
                    'approval_required' => $version->approval_required,
                    'limits' => $version->limits ?? [],
                    'requires_network' => $tool->requires_network,
                ];
            }

            return $result;
        });
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        $all = $this->all();

        return $all[$name] ?? null;
    }

    public function clearCache(): void
    {
        Cache::forget('agent_millow.tools.registry.v1');
    }
}
