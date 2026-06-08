<?php

declare(strict_types=1);

namespace Tests\Feature\Retention;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function inactiveUser(string $email, int $ageDays): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => false,
        ]);
        // Age the record beyond the window without tripping the updated_at touch.
        User::query()->whereKey($user->id)->update(['updated_at' => now()->subDays($ageDays)]);

        return $user->refresh();
    }

    public function test_dry_run_does_not_modify_anyone(): void
    {
        config(['retention.categories.inactive_users.days' => 365]);
        $u = $this->inactiveUser('left@bpjs.test', 400);

        $this->artisan('data:enforce-retention')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertSame('left@bpjs.test', $u->fresh()->email);
    }

    public function test_execute_anonymises_users_past_the_window(): void
    {
        config(['retention.categories.inactive_users.days' => 365]);
        $u = $this->inactiveUser('left@bpjs.test', 400);

        $this->artisan('data:enforce-retention --execute')->assertSuccessful();

        $fresh = $u->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('anonymized-'.$u->id.'@anonymized.invalid', $fresh->email);
        $this->assertSame('Pengguna Dianonimkan', $fresh->name);
    }

    public function test_ineligible_users_are_untouched(): void
    {
        config(['retention.categories.inactive_users.days' => 365]);
        $recent = $this->inactiveUser('recent@bpjs.test', 10);   // inside window
        $active = User::factory()->create(['email' => 'active@bpjs.test', 'is_active' => true]);
        User::query()->whereKey($active->id)->update(['updated_at' => now()->subDays(900)]);

        $this->artisan('data:enforce-retention --execute')->assertSuccessful();

        $this->assertSame('recent@bpjs.test', $recent->fresh()->email);
        $this->assertSame('active@bpjs.test', $active->fresh()->email);
    }

    public function test_bounded_window_guard_blocks_bulk_without_force(): void
    {
        config([
            'retention.categories.inactive_users.days' => 365,
            'retention.max_per_run' => 2,
        ]);
        for ($i = 0; $i < 3; $i++) {
            $this->inactiveUser("left{$i}@bpjs.test", 400);
        }

        $this->artisan('data:enforce-retention --execute')
            ->expectsOutputToContain('REFUSING to act')
            ->assertSuccessful();

        // None anonymised — all originals intact.
        $this->assertSame(3, User::query()->where('email', 'like', '%@bpjs.test')->count());
        $this->assertDatabaseHas('activity_logs', ['module' => 'retention', 'event' => 'enforce']);
    }

    public function test_force_bulk_overrides_the_guard(): void
    {
        config([
            'retention.categories.inactive_users.days' => 365,
            'retention.max_per_run' => 2,
        ]);
        for ($i = 0; $i < 3; $i++) {
            $this->inactiveUser("left{$i}@bpjs.test", 400);
        }

        $this->artisan('data:enforce-retention --execute --force-bulk')->assertSuccessful();

        $this->assertSame(3, User::query()->where('email', 'like', '%@anonymized.invalid')->count());
    }

    public function test_already_anonymised_users_are_idempotent(): void
    {
        config(['retention.categories.inactive_users.days' => 365]);
        $u = $this->inactiveUser('anonymized-999@anonymized.invalid', 400);

        $this->artisan('data:enforce-retention --execute')->assertSuccessful();

        // Excluded by the anonymised-email filter — left as-is.
        $this->assertSame('anonymized-999@anonymized.invalid', $u->fresh()->email);
    }

    public function test_run_is_audit_logged(): void
    {
        config(['retention.categories.inactive_users.days' => 365]);
        $this->inactiveUser('left@bpjs.test', 400);

        $this->artisan('data:enforce-retention --execute')->assertSuccessful();

        $log = ActivityLog::query()->where('module', 'retention')->where('event', 'enforce')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('executed', $log->context['outcome'] ?? null);
        $this->assertSame(1, $log->context['acted'] ?? null);
    }
}
