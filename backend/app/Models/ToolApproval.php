<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ToolApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tool_call_id
 * @property int $user_id
 * @property string $status
 * @property string|null $reason
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ToolApproval extends Model
{
    /** @use HasFactory<ToolApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'tool_call_id',
        'user_id',
        'status',
        'reason',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ToolCall, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(ToolCall::class, 'tool_call_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
