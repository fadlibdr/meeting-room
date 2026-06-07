<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Stage 4a — row-level tenant isolation.
 *
 * The global scope + stamping are registered unconditionally but evaluate
 * config('tenancy.enabled') + the TenantContext at QUERY/CREATE time, so:
 *  - tenancy OFF (default) → complete no-op (single-tenant unchanged),
 *  - tenancy ON with a context → reads are scoped to the tenant and new rows are
 *    stamped with it.
 * Checking config at runtime (not at model-boot) keeps it correct under tests
 * that toggle the flag after the model has booted.
 *
 * @property int|null $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            if (! config('tenancy.enabled')) {
                return;
            }
            $context = app(TenantContext::class);
            if ($context->check()) {
                $query->where($query->getModel()->getTable().'.tenant_id', $context->id());
            }
        });

        static::creating(function (Model $model): void {
            if (! config('tenancy.enabled')) {
                return;
            }
            $context = app(TenantContext::class);
            if ($context->check() && $model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $context->id());
            }
        });
    }
}
