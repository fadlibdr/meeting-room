<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\ProvisionTenantAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Stage 4 (4e) — provider console: the platform operator manages tenants.
 * Restricted to platform admins (super-admin of the default tenant).
 */
class ProviderTenantManager extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $slug = '';

    public string $primaryDomain = '';

    public string $feedback = '';

    private function guard(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403);
    }

    public function newTenant(): void
    {
        $this->guard();
        $this->reset(['name', 'slug', 'primaryDomain']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(ProvisionTenantAction $provisioner): void
    {
        $this->guard();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:60', Rule::unique('tenants', 'slug')],
            'primaryDomain' => ['nullable', 'string', 'max:160', Rule::unique('tenants', 'primary_domain')],
        ]);

        $tenant = $provisioner->provision(
            $validated['name'],
            $validated['slug'] ?: null,
            $validated['primaryDomain'] ?: null,
        );

        $this->feedback = __('Penyewa ":name" dibuat (slug: :slug).', ['name' => $tenant->name, 'slug' => $tenant->slug]);
        $this->showForm = false;
    }

    public function toggle(int $tenantId): void
    {
        $this->guard();
        $tenant = Tenant::findOrFail($tenantId);
        abort_if((bool) $tenant->is_default, 422); // never suspend the platform tenant
        $tenant->forceFill(['status' => $tenant->status === 'active' ? 'suspended' : 'active'])->save();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.provider-tenant-manager', [
            'tenants' => Tenant::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ])->layout('layouts.app', [
            'title' => __('Penyewa (Tenants)'),
            'subtitle' => __('Kelola organisasi pelanggan pada platform'),
        ]);
    }
}
