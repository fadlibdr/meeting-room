<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminI18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdminEn(): User
    {
        $user = User::factory()->create(['locale' => 'en']);
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_user_list_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Work Unit')
            ->assertSee('All roles')
            ->assertDontSee('Semua peran');
    }

    public function test_unit_list_renders_english(): void
    {
        $this->actingAs($this->superAdminEn())
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertSee('Parent')
            ->assertSee('Add Unit')
            ->assertDontSee('Tambah Unit');
    }
}
