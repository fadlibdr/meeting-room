<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function link(string $routeName): string
    {
        return 'href="'.route($routeName).'"';
    }

    public function test_super_admin_sees_all_nav_links(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get(route('dashboard'))
            ->assertSee($this->link('bookings.index'), false)
            ->assertSee($this->link('admin.rooms.index'), false)
            ->assertSee($this->link('admin.facilities.index'), false)
            ->assertSee($this->link('admin.room-blocks.index'), false)
            ->assertSee($this->link('admin.users.index'), false)
            ->assertSee($this->link('admin.logs.index'), false)
            ->assertSee($this->link('admin.settings.index'), false);
    }

    public function test_requester_sees_booking_links_but_no_admin_links(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('dashboard'))
            ->assertSee($this->link('bookings.index'), false)
            ->assertSee($this->link('calendar.index'), false)
            ->assertDontSee($this->link('admin.rooms.index'), false)
            ->assertDontSee($this->link('admin.facilities.index'), false)
            ->assertDontSee($this->link('admin.room-blocks.index'), false)
            ->assertDontSee($this->link('admin.users.index'), false)
            ->assertDontSee($this->link('admin.logs.index'), false)
            ->assertDontSee($this->link('admin.settings.index'), false);
    }

    public function test_ga_admin_sees_room_management_but_not_system_admin_links(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'))->get(route('dashboard'))
            ->assertSee($this->link('bookings.index'), false)
            ->assertSee($this->link('admin.rooms.index'), false)
            ->assertSee($this->link('admin.facilities.index'), false)
            ->assertSee($this->link('admin.room-blocks.index'), false)
            ->assertDontSee($this->link('admin.users.index'), false)
            ->assertDontSee($this->link('admin.logs.index'), false)
            ->assertDontSee($this->link('admin.settings.index'), false);
    }

    public function test_system_admin_sees_admin_links_but_not_room_management(): void
    {
        $this->actingAs($this->userWithRole('system_admin'))->get(route('dashboard'))
            ->assertSee($this->link('admin.users.index'), false)
            ->assertSee($this->link('admin.logs.index'), false)
            ->assertSee($this->link('admin.settings.index'), false)
            ->assertDontSee($this->link('admin.rooms.index'), false)
            ->assertDontSee($this->link('admin.facilities.index'), false)
            ->assertDontSee($this->link('admin.room-blocks.index'), false);
    }
}
