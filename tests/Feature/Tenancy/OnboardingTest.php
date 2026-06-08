<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\OnboardTenantAction;
use App\Livewire\TenantSignup;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboard_creates_a_tenant_with_an_owner_super_admin(): void
    {
        $owner = app(OnboardTenantAction::class)->onboard(
            ['name' => 'Contoh Org'],
            ['name' => 'Pemilik', 'email' => 'owner@contoh.test', 'password' => 'secret123'],
        );

        $tenant = Tenant::where('name', 'Contoh Org')->firstOrFail();
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertTrue(Hash::check('secret123', $owner->password));

        // The owner is super-admin OF THE NEW TENANT (its own role row).
        $ownerRole = $owner->roles()->withoutGlobalScope('tenant')->first();
        $this->assertNotNull($ownerRole);
        $this->assertSame('super_admin', $ownerRole->code);
        $this->assertSame($tenant->id, $ownerRole->tenant_id);
    }

    public function test_signup_page_404s_unless_enabled(): void
    {
        config(['tenancy.allow_signup' => false]);
        $this->get(route('tenant.signup'))->assertNotFound();

        config(['tenancy.allow_signup' => true]);
        $this->get(route('tenant.signup'))->assertOk()->assertSee('Daftarkan Organisasi');
    }

    public function test_self_service_signup_provisions_a_tenant_and_owner(): void
    {
        config(['tenancy.allow_signup' => true]);

        Livewire::test(TenantSignup::class)
            ->set('orgName', 'Startup Baru')
            ->set('name', 'Andi')
            ->set('email', 'andi@startup.test')
            ->set('password', 'password123')
            ->set('passwordConfirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('completed', true);

        $this->assertDatabaseHas('tenants', ['name' => 'Startup Baru']);
        $tenant = Tenant::where('name', 'Startup Baru')->firstOrFail();
        $this->assertDatabaseHas('users', ['email' => 'andi@startup.test', 'tenant_id' => $tenant->id]);
        $this->assertGreaterThan(0, Role::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count()); // tenant has its own roles
    }
}
