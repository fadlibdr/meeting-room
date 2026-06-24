<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Release B — idle + absolute session timeouts (SOC 2 CC6.1 / ISO 27001 A.8.5).
 */
class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'must_change_password' => false]);
    }

    public function test_idle_beyond_the_window_logs_out(): void
    {
        $this->actingAs($this->user())
            ->withSession([
                '_last_activity_at' => now()->subMinutes(31)->timestamp, // > 30m idle
                '_auth_started_at' => now()->timestamp,
            ])
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_recent_activity_keeps_the_session(): void
    {
        $this->actingAs($this->user())
            ->withSession([
                '_last_activity_at' => now()->subMinutes(5)->timestamp,
                '_auth_started_at' => now()->subMinutes(5)->timestamp,
            ])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_absolute_window_logs_out_even_when_active(): void
    {
        $this->actingAs($this->user())
            ->withSession([
                '_last_activity_at' => now()->timestamp,            // active right now
                '_auth_started_at' => now()->subMinutes(481)->timestamp, // > 480m absolute
            ])
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_idle_timeout_can_be_disabled(): void
    {
        app(SettingsService::class)->set('security.session_idle_timeout_minutes', '0');

        $this->actingAs($this->user())
            ->withSession([
                '_last_activity_at' => now()->subMinutes(120)->timestamp, // long idle
                '_auth_started_at' => now()->subMinutes(120)->timestamp,
            ])
            ->get('/dashboard')
            ->assertOk();
    }
}
