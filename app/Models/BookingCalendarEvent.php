<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stage 3 F.2b/c — booking -> external calendar event id mapping.
 *
 * @property int $id
 * @property int $booking_id
 * @property string $provider
 * @property int $target_user_id
 * @property string $external_event_id
 */
class BookingCalendarEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'booking_id', 'provider', 'target_user_id', 'external_event_id',
    ];
}
