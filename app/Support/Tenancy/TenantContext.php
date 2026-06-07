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
}
