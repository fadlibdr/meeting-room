<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Livewire\Admin\UserForm;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Release A — security audit logging (SOC 2 CC7.2 / ISO 27001 A.8.15) and the
 * append-only invariant (CC7.3).
 */
class SecurityAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);

        // Start hermetic: a sibling test using DatabaseMigrations (e.g. fresh-install)
        // can commit rows that survive into RefreshDatabase tests. Clear via the base
        // query builder, which bypasses the append-only model guard.
        DB::table('activity_logs')->delete();
    }

    private function securityLogs(?string $event = null): int
    {
        return ActivityLog::where('module', 'security')
            ->when($event, fn ($q) => $q->where('event', $event))
            ->count();
    }

    // ─── A1: authentication events via the subscriber ───────────────

    public function test_successful_login_is_logged(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        event(new Login('web', $user, false));

        $this->assertSame(1, $this->securityLogs('login'));
    }

    public function test_failed_login_is_logged_without_the_password(): void
    {
        event(new Failed('web', null, ['email' => 'attacker@example.com', 'password' => 'sekret']));

        $log = ActivityLog::where('module', 'security')->where('event', 'login.failed')->firstOrFail();
        $this->assertSame('attacker@example.com', $log->context['email'] ?? null);
        $encoded = json_encode($log->getAttributes());
        $this->assertStringNotContainsString('sekret', (string) $encoded, 'password must never be logged');
    }

    public function test_logout_and_lockout_are_logged(): void
    {
        $user = User::factory()->create();
        event(new Logout('web', $user));
        event(new Lockout(Request::create('/login', 'POST')));

        $this->assertSame(1, $this->securityLogs('logout'));
        $this->assertSame(1, $this->securityLogs('lockout'));
    }

    public function test_kill_switch_disables_audit_logging(): void
    {
        app(SettingsService::class)->set('security.audit_logging_enabled', '0');

        $user = User::factory()->create();
        event(new Login('web', $user, false));

        $this->assertSame(0, $this->securityLogs());
    }

    // ─── A1: privilege-change events ────────────────────────────────

    public function test_user_creation_via_admin_is_logged(): void
    {
        $admin = $this->adminUser();
        $unit = Unit::factory()->create();
        $role = Role::where('code', 'requester')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Pegawai Baru')
            ->set('email', 'pegawai.baru@bpjs-kesehatan.go.id')
            ->set('unitId', $unit->id)
            ->set('roleIds', [$role->id])
            ->call('save');

        $this->assertSame(1, $this->securityLogs('user.created'));
    }

    // ─── A2: append-only invariant ──────────────────────────────────

    public function test_activity_logs_cannot_be_updated(): void
    {
        $log = ActivityLog::create(['module' => 'security', 'event' => 'login', 'created_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $log->update(['event' => 'tampered']);
    }

    public function test_activity_logs_cannot_be_deleted_by_app_code(): void
    {
        $log = ActivityLog::create(['module' => 'security', 'event' => 'login', 'created_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $log->delete();
    }

    public function test_retention_prune_is_the_sanctioned_deletion_path(): void
    {
        ActivityLog::create(['module' => 'security', 'event' => 'login', 'created_at' => now()->subDays(400)]);
        ActivityLog::create(['module' => 'security', 'event' => 'login', 'created_at' => now()->subDays(10)]);

        $pruned = ActivityLog::pruneOlderThan(now()->subDays(365));

        $this->assertSame(1, $pruned);
        $this->assertSame(1, ActivityLog::count()); // the recent one survives
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('code', 'super_admin')->firstOrFail()->id, [
            'is_primary' => true, 'assigned_at' => now(),
        ]);

        return $user->fresh();
    }
}
