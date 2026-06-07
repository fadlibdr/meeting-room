<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Stage 4a — holds the current tenant id for the request/job (container
 * singleton). Web resolution (subdomain/domain) is P2; for the P0 spike + tests
 * the context is set explicitly.
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function check(): bool
    {
        return $this->tenantId !== null;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Run a callback within a specific tenant context (console/queue), restoring
     * the previous context afterwards. Use when there is no host to resolve from.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runFor(int $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
