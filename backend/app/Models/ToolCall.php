<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ToolCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $conversation_id
 * @property int $tool_id
 * @property int $tool_version_id
 * @property string $request_id
 * @property string $status
 * @property string $risk_level
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property array<string, mixed>|null $redacted_input
 * @property array<string, mixed>|null $redacted_output
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property Carbon|null $approved_at
 * @property Carbon|null $executed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ToolCall extends Model
{
    /** @use HasFactory<ToolCallFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'tool_id',
        'tool_version_id',
        'request_id',
        'status',
        'risk_level',
        'input',
        'output',
        'redacted_input',
        'redacted_output',
        'duration_ms',
        'error_message',
        'approved_at',
        'executed_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'redacted_input' => 'array',
            'redacted_output' => 'array',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * @return BelongsTo<ToolVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ToolVersion::class, 'tool_version_id');
    }

    /**
     * @return HasOne<ToolApproval, $this>
     */
    public function approval(): HasOne
    {
        return $this->hasOne(ToolApproval::class);
    }
}
