<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_user_id
 * @property string $module
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $description
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    /**
     * Internal flag: only the retention job may delete logs (via
     * {@see pruneOlderThan()}). Application code can never edit/delete entries.
     */
    protected static bool $pruning = false;

    protected $fillable = [
        'actor_user_id',
        'module', 'event',
        'subject_type', 'subject_id',
        'description',
        'old_values', 'new_values', 'context',
        'ip_address', 'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Append-only invariant (SOC 2 CC7.3): audit logs cannot be mutated, and can
     * only be deleted by the retention job — never by application code or a user.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('Activity logs are append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            if (! static::$pruning) {
                throw new \RuntimeException('Activity logs are append-only; prune via the retention job.');
            }
        });
    }

    /**
     * Delete log entries older than the cutoff. The ONLY sanctioned deletion
     * path — used by data:enforce-retention to apply the configured retention
     * window. Returns the number of rows removed.
     */
    public static function pruneOlderThan(\DateTimeInterface $cutoff): int
    {
        static::$pruning = true;

        try {
            return static::query()->where('created_at', '<', $cutoff)->delete();
        } finally {
            static::$pruning = false;
        }
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
