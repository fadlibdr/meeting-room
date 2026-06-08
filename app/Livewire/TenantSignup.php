<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\OnboardTenantAction;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Stage 4 (4c) — self-service onboarding. Creates a tenant + its owner admin,
 * then shows a confirmation with the workspace slug (the owner signs in via
 * their tenant's domain once it is configured — no cross-tenant auto-login).
 */
class TenantSignup extends Component
{
    public string $orgName = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $completed = false;

    public string $workspaceSlug = '';

    public function register(OnboardTenantAction $action): void
    {
        $validated = $this->validate([
            'orgName' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
        ]);

        $owner = $action->onboard(
            ['name' => $validated['orgName']],
            ['name' => $validated['name'], 'email' => $validated['email'], 'password' => $validated['password']],
        );

        $tenant = $owner->tenant;
        $this->workspaceSlug = $tenant instanceof Tenant ? $tenant->slug : '';
        $this->completed = true;
    }

    public function render(): View
    {
        return view('livewire.tenant-signup')->layout('layouts.guest');
    }
}
