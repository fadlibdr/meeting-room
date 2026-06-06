<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UserForm;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\EmailDomainPolicy;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EmailDomainRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'system_admin')->firstOrFail()->id]);

        return $user;
    }

    /**
     * Drive the create form with a given email and return the Livewire test.
     */
    private function submitWithEmail(string $email): Testable
    {
        $unit = Unit::factory()->create();
        $requesterRole = Role::where('code', 'requester')->firstOrFail()->id;

        return Livewire::actingAs($this->admin())
            ->test(UserForm::class)
            ->set('name', 'Test Pengguna')
            ->set('email', $email)
            ->set('unitId', $unit->id)
            ->set('roleIds', [$requesterRole])
            ->call('save');
    }

    public function test_default_restriction_rejects_foreign_domain(): void
    {
        $this->submitWithEmail('orang@gmail.com')->assertHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'orang@gmail.com']);
    }

    public function test_default_restriction_accepts_allowed_domain(): void
    {
        $this->submitWithEmail('orang@bpjs-kesehatan.go.id')->assertHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'orang@bpjs-kesehatan.go.id']);
    }

    public function test_disabling_restriction_accepts_any_domain(): void
    {
        app(SettingsService::class)->set('users.email_domain_restriction', false);

        $this->submitWithEmail('orang@gmail.com')->assertHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'orang@gmail.com']);
    }

    public function test_changing_the_domain_enforces_the_new_one(): void
    {
        app(SettingsService::class)->set('users.email_domain', 'contoso.com');

        // The new domain is accepted...
        $this->submitWithEmail('orang@contoso.com')->assertHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'orang@contoso.com']);

        // ...and the old default is now rejected.
        $this->submitWithEmail('lain@bpjs-kesehatan.go.id')->assertHasErrors('email');
    }

    public function test_policy_reflects_settings(): void
    {
        $policy = app(EmailDomainPolicy::class);
        $this->assertTrue($policy->enabled());
        $this->assertSame('bpjs-kesehatan.go.id', $policy->domain());
        $this->assertSame(['ends_with:@bpjs-kesehatan.go.id'], $policy->rules());

        app(SettingsService::class)->set('users.email_domain_restriction', false);
        $this->assertSame([], app(EmailDomainPolicy::class)->rules());
    }

    public function test_settings_page_exposes_the_new_controls(): void
    {
        $admin = $this->admin();
        $admin->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Batasi Domain Email')
            ->assertSee('Domain Email yang Diizinkan');
    }
}
