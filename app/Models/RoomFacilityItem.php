<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_id
 * @property int $room_facility_id
 * @property int $quantity
 * @property bool $is_operational
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RoomFacilityItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'room_facility_id', 'quantity', 'is_operational', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_operational' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(RoomFacility::class, 'room_facility_id');
    }
}
