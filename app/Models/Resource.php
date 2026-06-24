<?php

namespace App\Models;

use App\Enums\ResourceType;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A bookable resource (Stage 3 E). Rooms are the default type; equipment,
 * vehicles and desks share the same scheduling/conflict machinery. The
 * legacy {@see Room} model is a type-scoped subclass of this.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property ResourceType $type
 * @property string $code
 * @property string $name
 * @property string|null $location
 * @property string|null $floor
 * @property int $capacity
 * @property RoomStatus $status
 * @property RoomApprovalMode $approval_mode
 * @property int|null $approval_policy_id
 * @property int $booking_buffer_minutes
 * @property string|null $description
 * @property string|null $photo_path
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Resource extends Model
{
    use HasFactory;

    protected $table = 'resources';

    protected $fillable = [
        'type', 'code', 'name', 'location', 'floor', 'capacity',
        'status', 'approval_mode', 'approval_policy_id', 'booking_buffer_minutes',
        'description', 'photo_path', 'metadata', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'capacity' => 'integer',
            'status' => RoomStatus::class,
            'approval_mode' => RoomApprovalMode::class,
            'booking_buffer_minutes' => 'integer',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function facilityItems(): HasMany
    {
        return $this->hasMany(RoomFacilityItem::class, 'room_id');
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(RoomOperatingHour::class, 'room_id');
    }

    /**
     * @return BelongsTo<ApprovalPolicy, $this>
     */
    public function approvalPolicy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class);
    }

    public function blockSchedules(): HasMany
    {
        return $this->hasMany(RoomBlockSchedule::class, 'room_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
    }

    /**
     * Public URL for the resource photo, or null when none is set.
     */
    public function photoUrl(): ?string
    {
        $path = $this->photo_path;

        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
    }
}
