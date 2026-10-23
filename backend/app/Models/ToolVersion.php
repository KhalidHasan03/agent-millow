<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ToolVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tool_id
 * @property int $version
 * @property array<string, mixed> $input_schema
 * @property array<string, mixed>|null $output_schema
 * @property string $risk_level
 * @property array<int, string>|null $required_scopes
 * @property int $timeout_seconds
 * @property bool $approval_required
 * @property array<string, mixed>|null $limits
 * @property array<string, mixed>|null $metadata
 * @property bool $enabled
 */
final class ToolVersion extends Model
{
    /** @use HasFactory<ToolVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'tool_id',
        'version',
        'input_schema',
        'output_schema',
        'risk_level',
        'required_scopes',
        'timeout_seconds',
        'approval_required',
        'limits',
        'metadata',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'output_schema' => 'array',
            'required_scopes' => 'array',
            'limits' => 'array',
            'metadata' => 'array',
            'approval_required' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * @return HasMany<ToolCall, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(ToolCall::class);
    }
}
