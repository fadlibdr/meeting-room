<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Release C — TOTP two-factor authentication (SOC 2 CC6.1 / ISO 27001 A.8.5).
 */
class SecurityMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function userWithMfa(): array
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password' => Hash::make('Secret-Pass123'),
        ]);
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['AAAA-1111', 'BBBB-2222'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user->fresh(), $secret];
    }

    // ─── Enrollment ─────────────────────────────────────────────────

    public function test_user_can_enroll_with_a_valid_totp_code(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $component = Volt::actingAs($user)->test('auth.two-factor-setup');
        $secret = session('2fa_setup.secret');
        $this->assertNotEmpty($secret);

        $component->set('code', $this->otp($secret))
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('confirmed', true);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $component->get('recoveryCodes'));
    }

    public function test_enrollment_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        Volt::actingAs($user)->test('auth.two-factor-setup')
            ->set('code', '000000')
            ->call('confirm')
            ->assertHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    // ─── Login diversion + challenge ────────────────────────────────

    public function test_login_with_2fa_diverts_to_the_challenge(): void
    {
        [$user] = $this->userWithMfa();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'Secret-Pass123')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertTrue(session('2fa.pending'));
    }

    public function test_login_does_not_challenge_when_the_feature_is_disabled(): void
    {
        // Enrolled user, but the global 2FA feature is turned off → no challenge.
        [$user] = $this->userWithMfa();
        app(SettingsService::class)->set('security.mfa_enabled', '0');

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'Secret-Pass123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session('2fa.pending'));
    }

    public function test_challenge_passes_with_a_valid_totp_code(): void
    {
        [$user, $secret] = $this->userWithMfa();

        session(['2fa.pending' => true]);

        Volt::actingAs($user)->test('auth.two-factor-challenge')
            ->set('code', $this->otp($secret))
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session('2fa.pending'));
    }

    public function test_challenge_accepts_and_consumes_a_recovery_code(): void
    {
        [$user] = $this->userWithMfa();

        session(['2fa.pending' => true]);

        Volt::actingAs($user)->test('auth.two-factor-challenge')
            ->set('code', 'AAAA-1111')
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        // Code consumed — only the other one remains.
        $this->assertSame(['BBBB-2222'], array_values($user->fresh()->two_factor_recovery_codes));
    }

    public function test_challenge_rejects_a_bad_code(): void
    {
        [$user] = $this->userWithMfa();

        session(['2fa.pending' => true]);

        Volt::actingAs($user)->test('auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code');
    }

    // ─── Gate / enforcement ─────────────────────────────────────────

    public function test_pending_session_is_confined_to_the_challenge(): void
    {
        [$user] = $this->userWithMfa();

        $this->actingAs($user)->withSession(['2fa.pending' => true])
            ->get('/dashboard')
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_privileged_user_is_forced_to_enroll_when_enforced(): void
    {
        // Enforcement is opt-in (off by default) — turn it on for this case.
        app(SettingsService::class)->set('security.mfa_enforced_for_privileged', '1');

        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'super_admin')->firstOrFail()->id, [
            'is_primary' => true, 'assigned_at' => now(),
        ]);

        $this->actingAs($admin->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('two-factor.setup'));
    }

    public function test_feature_disabled_means_no_enforcement(): void
    {
        app(SettingsService::class)->set('security.mfa_enabled', '0');

        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'super_admin')->firstOrFail()->id, [
            'is_primary' => true, 'assigned_at' => now(),
        ]);

        $this->actingAs($admin->fresh())->get('/dashboard')->assertOk();
    }

    public function test_non_privileged_user_is_not_forced_by_default(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'requester')->firstOrFail()->id, [
            'is_primary' => true, 'assigned_at' => now(),
        ]);

        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }
}
