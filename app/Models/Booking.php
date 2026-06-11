<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomLayout;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $booking_code
 * @property int $resource_id
 * @property int $requester_user_id
 * @property int|null $requester_unit_id
 * @property int $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string $subject
 * @property string|null $agenda
 * @property int $attendee_count
 * @property RoomLayout|null $room_layout
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property BookingStatus $status
 * @property string $source
 * @property RoomApprovalMode $approval_mode_snapshot
 * @property int|null $current_approval_step
 * @property int|null $current_approver_user_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $released_at
 * @property string|null $rejection_reason
 * @property string|null $cancellation_reason
 * @property int|null $rescheduled_from_booking_id
 * @property int|null $recurrence_group_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Booking extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasHashid;

    protected $fillable = [
        'booking_code', 'resource_id',
        'requester_user_id', 'requester_unit_id',
        'created_by_user_id', 'updated_by_user_id',
        'subject', 'agenda', 'attendee_count', 'room_layout',
        'starts_at', 'ends_at',
        'status', 'source', 'approval_mode_snapshot',
        'current_approval_step', 'current_approver_user_id',
        'submitted_at', 'approved_at', 'rejected_at',
        'cancelled_at', 'completed_at', 'reminder_sent_at', 'checked_in_at', 'released_at',
        'rejection_reason', 'cancellation_reason',
        'rescheduled_from_booking_id', 'recurrence_group_id',
    ];

    protected function casts(): array
    {
        return [
            'attendee_count' => 'integer',
            'room_layout' => RoomLayout::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'approval_mode_snapshot' => RoomApprovalMode::class,
            'current_approval_step' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'resource_id');
    }

    /**
     * The bookable resource (generalized; unscoped by type).
     *
     * Shares the `resource_id` column with {@see room()} but resolves to the
     * base {@see Resource} so non-room resources are reachable (Stage 3 E).
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function requesterUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'requester_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_user_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(BookingApproval::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BookingAttachment::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_booking_id');
    }

    public function recurrenceGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_group_id');
    }

    /**
     * All bookings in the same recurring series (including this one), keyed by
     * the shared recurrence_group_id (the anchor occurrence's id).
     */
    public function recurrenceOccurrences(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_group_id', 'recurrence_group_id');
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_group_id !== null;
    }

    public function isAutoReleased(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * Bookings auto-cancelled as no-shows by bookings:auto-release
     * (released_at stamped). Drives the no-show metric (Stage 3 A.2).
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeAutoReleased(Builder $query): Builder
    {
        return $query->whereNotNull('released_at');
    }
}
