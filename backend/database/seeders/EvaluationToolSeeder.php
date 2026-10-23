<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\ToolVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds the evaluation-only guarded tool. It is created only in the disposable
 * in-memory evaluation database and is never part of the application seed set.
 */
final class EvaluationToolSeeder extends Seeder
{
    public function run(): void
    {
        $tool = Tool::query()->firstOrCreate(
            ['name' => 'eval_guarded'],
            [
                'display_name' => 'Evaluation Guarded Tool',
                'description' => 'High-risk tool used only to verify approval enforcement offline.',
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
                    ],
                ],
                'risk_level' => 'high',
                'required_scopes' => [],
                'timeout_seconds' => 10,
                'approval_required' => true,
                'limits' => [],
                'metadata' => ['evaluation_only' => true],
                'enabled' => true,
            ],
        );
    }
}
