<?php

namespace App\Models;

use App\Enums\RoomBlockType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_id
 * @property int|null $recurrence_group_id
 * @property RoomBlockType $block_type
 * @property string $title
 * @property string|null $reason
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $created_by_user_id
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RoomBlockSchedule extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'room_id', 'recurrence_group_id', 'block_type', 'title', 'reason',
        'starts_at', 'ends_at',
        'created_by_user_id', 'cancelled_at', 'cancelled_by_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'block_type' => RoomBlockType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function recurrenceGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_group_id');
    }

    /**
     * All blocks in the same recurring series (including this one).
     */
    public function recurrenceOccurrences(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_group_id', 'recurrence_group_id');
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_group_id !== null;
    }
}
