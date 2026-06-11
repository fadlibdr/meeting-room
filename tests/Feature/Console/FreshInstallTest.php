<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class FreshInstallTest extends TestCase
{
    use DatabaseMigrations;

    public function test_fresh_install_creates_a_forced_default_superadmin(): void
    {
        // Pre-existing data that must be wiped.
        User::factory()->create(['email' => 'old@bpjs.test']);

        $this->artisan('app:fresh-install', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'old@bpjs.test']);

        $admin = User::where('email', 'superadmin@bpjs.local')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->roles()->where('code', 'super_admin')->exists());

        // Exactly one user remains.
        $this->assertSame(1, User::count());
    }
}
