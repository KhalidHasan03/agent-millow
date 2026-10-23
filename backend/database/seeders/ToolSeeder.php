<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\ToolVersion;
use App\Services\Tools\Registry\ToolRegistry;
use Illuminate\Database\Seeder;

final class ToolSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEcho();

        if (app()->environment(['local', 'testing'])) {
            $this->seedGuardedDemo();
        }

        app(ToolRegistry::class)->clearCache();
    }

    private function seedEcho(): void
    {
        $tool = Tool::query()->firstOrCreate(
            ['name' => 'echo'],
            [
                'display_name' => 'Echo',
                'description' => 'Echoes a message back. Safe read-only diagnostic tool.',
                'enabled' => true,
                'requires_network' => false,
            ],
        );

        ToolVersion::query()->firstOrCreate(
            ['tool_id' => $tool->id, 'version' => 1],
            [
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['message'],
                    'properties' => [
                        'message' => ['type' => 'string', 'maxLength' => 500],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'echo' => ['type' => 'string'],
                        'user_id' => ['type' => 'integer'],
                    ],
                ],
                'risk_level' => 'low',
                'required_scopes' => [],
                'timeout_seconds' => 10,
                'approval_required' => false,
                'limits' => [],
                'metadata' => ['category' => 'utility'],
                'enabled' => true,
            ],
        );
    }

    private function seedGuardedDemo(): void
    {
        $tool = Tool::query()->firstOrCreate(
            ['name' => 'echo_guarded'],
            [
                'display_name' => 'Echo (approval required)',
                'description' => 'Echoes a message back after approval. Demo tool for exercising the approval workflow.',
                'enabled' => true,
                'requires_network' => false,
            ],
        );

        ToolVersion::query()->firstOrCreate(
            ['tool_id' => $tool->id, 'version' => 1],
            [
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['message'],
                    'properties' => [
                        'message' => ['type' => 'string', 'maxLength' => 500],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'echo' => ['type' => 'string'],
                        'user_id' => ['type' => 'integer'],
                    ],
                ],
                'risk_level' => 'high',
                'required_scopes' => [],
                'timeout_seconds' => 10,
                'approval_required' => true,
                'limits' => [],
                'metadata' => ['category' => 'utility', 'demo' => true],
                'enabled' => true,
            ],
        );
    }
}
