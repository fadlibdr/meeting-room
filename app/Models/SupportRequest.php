<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupportCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stage 4g.1 — an in-app support/contact request. Emailed to support AND
 * persisted for tracking. Tenant-scoped.
 *
 * @property SupportCategory $category
 * @property int $id
 * @property string|null $subject
 * @property string $message
 */
class SupportRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'category',
        'subject',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupportCategory::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
