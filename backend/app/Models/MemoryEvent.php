<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemoryEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $memory_id
 * @property string $action
 * @property string|null $actor_type
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 */
class MemoryEvent extends Model
{
    /** @use HasFactory<MemoryEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'memory_id',
        'action',
        'actor_type',
        'request_id',
        'ip_address',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<Memory, $this>
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }
}
