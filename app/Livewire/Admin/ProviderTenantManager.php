<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\ProvisionTenantAction;
use App\Models\Booking;
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

    // 4b white-label branding edit
    public ?int $editingId = null;

    public string $brandName = '';

    public string $brandColor = '';

    public string $logoUrl = '';

    // 4e per-tenant feature flags edit
    public ?int $editingFeaturesId = null;

    /** @var array<string, bool> */
    public array $featureFlags = [];

    /** Toggleable per-tenant features (key => label). */
    public const FEATURES = [
        'calendar_sync' => 'Sinkronisasi Kalender',
        'webhooks' => 'Webhook',
        'exports' => 'Ekspor Data',
        'public_api' => 'API Publik',
    ];

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

    public function editBranding(int $tenantId): void
    {
        $this->guard();
        $tenant = Tenant::findOrFail($tenantId);
        $this->editingId = $tenant->id;
        $this->brandName = $tenant->brand_name ?? '';
        $this->brandColor = $tenant->brand_color ?? '';
        $this->logoUrl = $tenant->logo_url ?? '';
        $this->resetValidation();
    }

    public function saveBranding(): void
    {
        $this->guard();
        if ($this->editingId === null) {
            return;
        }

        $validated = $this->validate([
            'brandName' => ['nullable', 'string', 'max:120'],
            'brandColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logoUrl' => ['nullable', 'url', 'max:255'],
        ]);

        Tenant::findOrFail($this->editingId)->update([
            'brand_name' => $validated['brandName'] ?: null,
            'brand_color' => $validated['brandColor'] ?: null,
            'logo_url' => $validated['logoUrl'] ?: null,
        ]);

        $this->feedback = __('Branding penyewa diperbarui.');
        $this->editingId = null;
    }

    public function cancelBranding(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    public function editFeatures(int $tenantId): void
    {
        $this->guard();
        $tenant = Tenant::findOrFail($tenantId);
        $current = $tenant->features ?? [];
        $this->featureFlags = [];
        foreach (array_keys(self::FEATURES) as $key) {
            $this->featureFlags[$key] = (bool) ($current[$key] ?? false);
        }
        $this->editingFeaturesId = $tenant->id;
        $this->resetValidation();
    }

    public function saveFeatures(): void
    {
        $this->guard();
        if ($this->editingFeaturesId === null) {
            return;
        }

        // Only persist known flags (cast to bool).
        $clean = [];
        foreach (array_keys(self::FEATURES) as $key) {
            $clean[$key] = (bool) ($this->featureFlags[$key] ?? false);
        }

        Tenant::findOrFail($this->editingFeaturesId)->update(['features' => $clean]);
        $this->feedback = __('Fitur penyewa diperbarui.');
        $this->editingFeaturesId = null;
    }

    public function cancelFeatures(): void
    {
        $this->editingFeaturesId = null;
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
        // Per-tenant usage (counted unscoped — the console spans all tenants).
        $userCounts = User::withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, COUNT(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $bookingCounts = Booking::withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, COUNT(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        return view('livewire.admin.provider-tenant-manager', [
            'tenants' => Tenant::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'userCounts' => $userCounts,
            'bookingCounts' => $bookingCounts,
            'featureLabels' => self::FEATURES,
        ])->layout('layouts.app', [
            'title' => __('Penyewa (Tenants)'),
            'subtitle' => __('Kelola organisasi pelanggan pada platform'),
        ]);
    }
}
