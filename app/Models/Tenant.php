<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stage 4a — a customer tenant. Carries white-label branding (4b) + a feature
 * flag bag (4e).
 *
 * @property int $id
 * @property string $name
 * @property string|null $brand_name
 * @property string|null $brand_color
 * @property string|null $logo_url
 * @property string|null $email_from_name
 * @property string|null $email_from_address
 * @property array<string, bool>|null $features
 * @property string $slug
 * @property string|null $primary_domain
 * @property string $status
 * @property bool $is_default
 */
class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'brand_name', 'brand_color', 'logo_url',
        'email_from_name', 'email_from_address', 'features',
        'slug', 'primary_domain', 'status',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /** A per-tenant feature flag (4e). Unknown flags default to $default. */
    public function feature(string $key, bool $default = false): bool
    {
        return (bool) (($this->features ?? [])[$key] ?? $default);
    }
}
