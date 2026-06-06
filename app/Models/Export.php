<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user-requested data export (Stage 2.2).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property ExportFormat $format
 * @property ExportStatus $status
 * @property string $scope
 * @property array<string, mixed>|null $filters
 * @property string|null $filename
 * @property string|null $path
 * @property int|null $row_count
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory;

    /** Disk the generated file is written to / read from. */
    public const DISK = 'local_private';

    protected $fillable = [
        'user_id', 'type', 'format', 'status', 'scope',
        'filters', 'filename', 'path', 'row_count', 'error',
        'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'filters' => 'array',
            'row_count' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Completed
            && $this->path !== null
            && ! $this->isExpired();
    }

    /**
     * Expired rows whose files are due for pruning.
     *
     * @param  Builder<Export>  $query
     * @return Builder<Export>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }
}
