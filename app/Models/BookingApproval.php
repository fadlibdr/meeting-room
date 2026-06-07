<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $sequence_no
 * @property int $approver_user_id
 * @property string $status
 * @property Carbon|null $action_at
 * @property string|null $action_notes
 * @property int|null $acted_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BookingApproval extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'booking_id', 'sequence_no',
        'approver_user_id', 'status',
        'action_at', 'action_notes', 'acted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'action_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }
}
