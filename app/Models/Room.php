<?php

namespace App\Models;

use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $location
 * @property string|null $floor
 * @property int $capacity
 * @property RoomStatus $status
 * @property RoomApprovalMode $approval_mode
 * @property int $booking_buffer_minutes
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'location', 'floor', 'capacity',
        'status', 'approval_mode', 'booking_buffer_minutes',
        'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => RoomStatus::class,
            'approval_mode' => RoomApprovalMode::class,
            'booking_buffer_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function facilityItems(): HasMany
    {
        return $this->hasMany(RoomFacilityItem::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(RoomOperatingHour::class);
    }

    public function blockSchedules(): HasMany
    {
        return $this->hasMany(RoomBlockSchedule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
