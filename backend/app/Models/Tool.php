<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string $description
 * @property bool $enabled
 * @property bool $requires_network
 */
final class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'enabled',
        'requires_network',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'requires_network' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ToolVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ToolVersion::class);
    }

    /**
     * @return HasMany<ToolCall, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(ToolCall::class);
    }
}
