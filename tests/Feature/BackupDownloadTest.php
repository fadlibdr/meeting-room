<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createUserWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function fakeDumper(): void
    {
        $this->mock(DatabaseBackupService::class, function ($mock) {
            $tmp = tempnam(sys_get_temp_dir(), 'testbk_');
            file_put_contents($tmp, gzencode('-- fake dump'));
            $mock->shouldReceive('dumpToTempFile')->once()->andReturn($tmp);
            $mock->shouldReceive('suggestedFilename')
                ->andReturn('meeting_room_test-2026-01-01-000000.sql.gz');
        });
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('admin.backup.download'))
            ->assertRedirect(route('login'));
    }

    public function test_requester_cannot_download_backup(): void
    {
        $user = $this->createUserWithRole('requester');

        $this->actingAs($user)
            ->post(route('admin.backup.download'))
            ->assertForbidden();
    }

    public function test_ga_admin_cannot_download_backup(): void
    {
        $user = $this->createUserWithRole('ga_admin');

        $this->actingAs($user)
            ->post(route('admin.backup.download'))
            ->assertForbidden();
    }

    public function test_super_admin_can_download_backup(): void
    {
        $this->fakeDumper();
        $user = $this->createUserWithRole('super_admin');

        $this->actingAs($user)
            ->post(route('admin.backup.download'))
            ->assertOk()
            ->assertDownload('meeting_room_test-2026-01-01-000000.sql.gz');
    }

    public function test_system_admin_can_download_backup(): void
    {
        $this->fakeDumper();
        $user = $this->createUserWithRole('system_admin');

        $this->actingAs($user)
            ->post(route('admin.backup.download'))
            ->assertOk()
            ->assertDownload('meeting_room_test-2026-01-01-000000.sql.gz');
    }
}
