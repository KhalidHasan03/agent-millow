<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Retention pruning is the only sanctioned deletion path. The flag is
     * private so mass assignment and ordinary model writes cannot set it.
     */
    private bool $allowDeletion = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
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

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit events are append-only and cannot be updated.');
        });

        static::deleting(function (AuditEvent $event): void {
            if (! $event->allowDeletion) {
                throw new LogicException('Audit events can only be deleted by the retention command.');
            }
        });
    }

    /**
     * Delete an expired event as part of the sanctioned retention process.
     */
    public function deleteForRetention(): bool
    {
        $this->allowDeletion = true;

        return $this->delete();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
