<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * DESTRUCTIVE — wipes ALL data and recreates a clean install with a single
 * default super-admin that must change its password on first login.
 *
 *   php artisan app:fresh-install
 *   php artisan app:fresh-install --force   # skip the confirmation prompt
 *
 * Email/password default to superadmin@bpjs.local / superadmin and can be
 * overridden via DEFAULT_SUPERADMIN_EMAIL / DEFAULT_SUPERADMIN_PASSWORD.
 */
class FreshInstall extends Command
{
    protected $signature = 'app:fresh-install {--force : Skip the confirmation prompt}';

    protected $description = 'DESTRUCTIVE: wipe all data and create a default super-admin (must change password on first login).';

    public function handle(): int
    {
        $email = (string) config('app.default_superadmin_email', 'superadmin@bpjs.local');
        $password = (string) config('app.default_superadmin_password', 'superadmin');

        $this->warn('This will DELETE ALL DATA and recreate a clean install.');
        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // 1. Wipe + remigrate (recreates schema, default tenant, cache tables, …).
        $this->call('migrate:fresh', ['--force' => true]);

        // 2. Seed the role/permission catalog + default app settings.
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => AppSettingsSeeder::class, '--force' => true]);

        // 3. The single default super-admin, forced to change its password.
        DB::transaction(function () use ($email, $password): void {
            $user = User::create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);

            $superAdmin = Role::where('code', 'super_admin')->firstOrFail();
            $user->roles()->sync([$superAdmin->id]);
        });

        $this->newLine();
        $this->info('Fresh install complete.');
        $this->line('  Login email : '.$email);
        $this->line('  Password    : '.$password.'  (must be changed on first login)');

        return self::SUCCESS;
    }
}
