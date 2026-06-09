<?php

declare(strict_types=1);

namespace Tests\Feature\Nav;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollapsibleSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_sidebar_renders_collapsible_groups_for_admin(): void
    {
        $res = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();

        // Group headers (clickable, with persistence + Alpine toggle).
        $res->assertSee('nav__grouphead', false);
        $res->assertSee('navgrp:rooms', false);     // localStorage key from the component
        $res->assertSee('Manajemen Ruang', false);  // a group label
        $res->assertSee('Sistem', false);

        // Items still present inside their groups.
        $res->assertSee(route('admin.rooms.index'), false);
        $res->assertSee(route('admin.settings.index'), false);
    }

    public function test_active_group_is_expanded_by_default(): void
    {
        // On an admin rooms page, the "rooms" group should auto-open (open=true).
        $res = $this->actingAs($this->admin())->get(route('admin.rooms.index'))->assertOk();

        // The component renders x-data with open:true when its route is active.
        $res->assertSee('x-data="{ open: true }"', false);
    }

    public function test_dashboard_stays_a_standalone_item(): void
    {
        // Dashboard is not wrapped in a collapsible group — always reachable.
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('dashboard'), false);
    }
}
