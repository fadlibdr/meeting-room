<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stage 4a P0 spike — a customer tenant.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $primary_domain
 * @property string $status
 */
class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'primary_domain', 'status'];
}
