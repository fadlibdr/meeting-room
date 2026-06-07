<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AzureSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function enableSso(): void
    {
        config([
            'sso.enabled' => true,
            'sso.auto_provision' => true,
            'sso.default_role' => 'requester',
            'sso.group_role_map' => ['GRP-GA' => 'ga_admin'],
        ]);
    }

    /**
     * @param  list<string>  $groups
     */
    private function fakeIdentity(string $email, string $name, array $groups = []): SocialiteUser
    {
        $identity = new SocialiteUser;
        $identity->map(['id' => 'aad-'.md5($email), 'name' => $name, 'email' => $email]);
        $identity->user = ['groups' => $groups];

        return $identity;
    }

    private function mockSocialiteReturns(SocialiteUser $identity): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($identity);
        Socialite::shouldReceive('driver')->with('azure')->andReturn($driver);
    }

    public function test_sso_routes_404_when_disabled(): void
    {
        config(['sso.enabled' => false]);

        $this->get(route('sso.azure.redirect'))->assertNotFound();
        $this->get(route('sso.azure.callback'))->assertNotFound();
    }

    public function test_login_page_shows_sso_button_only_when_enabled(): void
    {
        config(['sso.enabled' => false]);
        $this->get(route('login'))->assertOk()->assertDontSee('Masuk dengan Microsoft');

        config(['sso.enabled' => true]);
        $this->get(route('login'))->assertOk()->assertSee('Masuk dengan Microsoft');
    }

    public function test_callback_provisions_a_new_user_with_group_mapped_role_and_logs_in(): void
    {
        $this->enableSso();
        $this->mockSocialiteReturns($this->fakeIdentity('budi@bpjs.go.id', 'Budi Santoso', ['GRP-GA']));

        $this->get(route('sso.azure.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $user = User::where('email', 'budi@bpjs.go.id')->firstOrFail();
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->roles->contains('code', 'ga_admin'));
    }

    public function test_callback_assigns_default_role_when_no_group_maps(): void
    {
        $this->enableSso();
        $this->mockSocialiteReturns($this->fakeIdentity('sari@bpjs.go.id', 'Sari', []));

        $this->get(route('sso.azure.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'sari@bpjs.go.id')->firstOrFail();
        $this->assertTrue($user->roles->contains('code', 'requester'));
    }

    public function test_callback_links_existing_user_without_clobbering_roles(): void
    {
        $this->enableSso();
        $existing = User::factory()->create(['email' => 'admin@bpjs.go.id', 'is_active' => true]);
        $existing->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        // No groups claim → existing roles must be preserved.
        $this->mockSocialiteReturns($this->fakeIdentity('admin@bpjs.go.id', 'Admin', []));

        $this->get(route('sso.azure.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertTrue($existing->fresh()->roles->contains('code', 'super_admin'));
        $this->assertSame(1, User::where('email', 'admin@bpjs.go.id')->count());
    }

    public function test_inactive_user_cannot_sign_in_via_sso(): void
    {
        $this->enableSso();
        User::factory()->create(['email' => 'gone@bpjs.go.id', 'is_active' => false]);
        $this->mockSocialiteReturns($this->fakeIdentity('gone@bpjs.go.id', 'Gone', []));

        $this->get(route('sso.azure.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
