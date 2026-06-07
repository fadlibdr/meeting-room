<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ApplyTenantBranding;
use App\Livewire\Admin\ProviderTenantManager;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

class WhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    private function applyBrandingFor(?Tenant $tenant): array
    {
        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant->id);
        }
        (new ApplyTenantBranding)->handle(Request::create('http://x/'), fn ($r) => response('ok'));

        return View::shared('branding');
    }

    public function test_applies_tenant_branding_when_resolved(): void
    {
        config(['tenancy.enabled' => true]);
        $tenant = Tenant::factory()->create([
            'brand_name' => 'Acme Corp', 'brand_color' => '#123456', 'logo_url' => 'https://acme.test/logo.png',
            'email_from_address' => 'noreply@acme.test', 'email_from_name' => 'Acme',
        ]);

        $branding = $this->applyBrandingFor($tenant);

        $this->assertSame('Acme Corp', $branding['name']);
        $this->assertSame('#123456', $branding['color']);
        $this->assertSame('https://acme.test/logo.png', $branding['logo_url']);
        $this->assertSame('Acme Corp', config('app.name'));
        $this->assertSame('noreply@acme.test', config('mail.from.address'));
    }

    public function test_falls_back_to_default_branding(): void
    {
        config(['tenancy.enabled' => false]);

        $branding = $this->applyBrandingFor(null);

        $this->assertSame('#005490', $branding['color']);
        $this->assertNull($branding['logo_url']);
    }

    public function test_feature_flag_helper_reads_the_bag(): void
    {
        $tenant = Tenant::factory()->create(['features' => ['beta_calendar' => true]]);

        $this->assertTrue($tenant->feature('beta_calendar'));
        $this->assertFalse($tenant->feature('missing'));
        $this->assertTrue($tenant->feature('missing', true));
    }

    public function test_platform_admin_can_edit_tenant_branding(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);
        $target = Tenant::factory()->create();

        Livewire::actingAs($admin->refresh())
            ->test(ProviderTenantManager::class)
            ->call('editBranding', $target->id)
            ->set('brandName', 'Rebranded')
            ->set('brandColor', '#abcdef')
            ->call('saveBranding')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', ['id' => $target->id, 'brand_name' => 'Rebranded', 'brand_color' => '#abcdef']);
    }
}
