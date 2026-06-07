<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_id
 * @property int $day_of_week
 * @property string|null $open_time
 * @property string|null $close_time
 * @property bool $is_closed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RoomOperatingHour extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'room_id', 'day_of_week', 'open_time', 'close_time', 'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
