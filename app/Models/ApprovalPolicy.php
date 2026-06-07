<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ApprovalPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, reusable multi-step approval chain (Stage 3 B).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
class ApprovalPolicy extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalPolicyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<ApprovalPolicyStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalPolicyStep::class)->orderBy('sequence_no');
    }
}
