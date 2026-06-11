<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $original_name
 * @property string $stored_name
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int $uploaded_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BookingAttachment extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasHashid;

    protected $fillable = [
        'booking_id',
        'original_name', 'stored_name',
        'disk', 'path', 'mime_type', 'size_bytes',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
