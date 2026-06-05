<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SystemHealthNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush(); // clear any throttle key from a prior test
    }

    private function admin(string $roleCode = 'super_admin'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', $roleCode)->firstOrFail()->id]);

        return $user;
    }

    private function seedStaleJob(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->subMinutes(10)->getTimestamp(),
        ]);
    }

    public function test_healthy_system_exits_zero_and_sends_no_alert(): void
    {
        Notification::fake();
        $this->admin();

        $this->artisan('system:health-check')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_stale_job_fails_and_notifies_admins(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->seedStaleJob();

        $this->artisan('system:health-check')->assertExitCode(1);

        Notification::assertSentTo($admin, SystemHealthNotification::class);
    }

    public function test_failed_jobs_backlog_triggers_an_alert(): void
    {
        Notification::fake();
        $admin = $this->admin();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ]);

        $this->artisan('system:health-check')->assertExitCode(1);

        Notification::assertSentTo($admin, SystemHealthNotification::class);
    }

    public function test_mail_misconfiguration_is_flagged(): void
    {
        Notification::fake();
        $admin = $this->admin();

        AppSetting::create([
            'key' => 'notifications.send_email_default',
            'value' => '1',
            'data_type' => 'boolean',
            'label' => 'Email default',
            'group' => 'notifications',
            'is_editable' => true,
        ]);
        config(['mail.default' => 'log']);

        $this->artisan('system:health-check')->assertExitCode(1);

        Notification::assertSentTo(
            $admin,
            SystemHealthNotification::class,
            fn (SystemHealthNotification $n): bool => collect($n->toArray($admin)['issues'])
                ->contains(fn (string $i): bool => str_contains($i, 'mailer masih "log"')),
        );
    }

    public function test_alert_is_throttled_within_the_window(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->seedStaleJob();

        $this->artisan('system:health-check')->assertExitCode(1);
        $this->artisan('system:health-check')->assertExitCode(1); // still failing, but throttled

        Notification::assertSentToTimes($admin, SystemHealthNotification::class, 1);
    }

    public function test_only_users_with_app_settings_update_are_alerted(): void
    {
        Notification::fake();
        $admin = $this->admin('super_admin');
        $requester = $this->admin('requester'); // not an app-settings.update holder
        $this->seedStaleJob();

        $this->artisan('system:health-check')->assertExitCode(1);

        Notification::assertSentTo($admin, SystemHealthNotification::class);
        Notification::assertNotSentTo($requester, SystemHealthNotification::class);
    }
}
