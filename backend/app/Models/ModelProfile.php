<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModelProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $name
 * @property string|null $label
 * @property int|null $context_window
 * @property int|null $max_output_tokens
 * @property bool $supports_streaming
 * @property bool $supports_tools
 * @property bool $is_default
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ModelProfile extends Model
{
    /** @use HasFactory<ModelProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'name',
        'label',
        'context_window',
        'max_output_tokens',
        'supports_streaming',
        'supports_tools',
        'is_default',
        'is_active',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'max_output_tokens' => 'integer',
            'supports_streaming' => 'boolean',
            'supports_tools' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
