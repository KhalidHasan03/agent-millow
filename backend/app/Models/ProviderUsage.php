<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProviderUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $conversation_id
 * @property int|null $message_id
 * @property string $provider
 * @property string|null $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property int|null $latency_ms
 * @property string $status
 * @property string|null $error_code
 * @property string|null $estimated_cost
 * @property string|null $request_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProviderUsage extends Model
{
    /** @use HasFactory<ProviderUsageFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'provider_usage';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'status',
        'error_code',
        'estimated_cost',
        'request_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
