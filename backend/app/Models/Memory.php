<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemoryCategory;
use App\Enums\MemorySource;
use Database\Factories\MemoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $key
 * @property string $title
 * @property string $content
 * @property MemoryCategory $category
 * @property MemorySource $source
 * @property int $confidence
 * @property bool $sensitive
 * @property bool $pinned
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $last_accessed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'title',
        'content',
        'category',
        'source',
        'confidence',
        'sensitive',
        'pinned',
        'metadata',
        'last_accessed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MemoryCategory::class,
            'source' => MemorySource::class,
            'confidence' => 'integer',
            'sensitive' => 'boolean',
            'pinned' => 'boolean',
            'metadata' => 'array',
            'last_accessed_at' => 'datetime',
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
     * @return HasMany<MemoryEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MemoryEvent::class);
    }
}
