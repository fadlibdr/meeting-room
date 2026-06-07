<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Stage 3 F.2b/c — a user's delegated calendar OAuth connection.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $external_calendar_id
 * @property bool $is_active
 */
class CalendarConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'provider', 'access_token', 'refresh_token',
        'token_expires_at', 'external_calendar_id', 'is_active',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
