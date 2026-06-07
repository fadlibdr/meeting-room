<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ResourceType;
use App\Livewire\Admin\ResourceManager;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceManagementTest extends TestCase
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

    public function test_super_admin_can_view_resources_index(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.resources.index'))->assertOk();
    }

    public function test_requester_cannot_view_resources_index(): void
    {
        $this->actingAs($this->userWithRole('requester'))
            ->get(route('admin.resources.index'))->assertForbidden();
    }

    public function test_admin_can_create_a_non_room_resource(): void
    {
        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ResourceManager::class)
            ->call('newResource')
            ->set('type', 'vehicle')
            ->set('code', 'VHC-01')
            ->set('name', 'Mobil Dinas Avanza 1')
            ->set('capacity', 7)
            ->set('approvalMode', 'none')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('resources', [
            'code' => 'VHC-01',
            'type' => 'vehicle',
            'name' => 'Mobil Dinas Avanza 1',
            'capacity' => 7,
        ]);
    }

    public function test_resource_type_must_not_be_room(): void
    {
        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ResourceManager::class)
            ->call('newResource')
            ->set('type', 'room')
            ->set('code', 'X-01')
            ->set('name', 'Sneaky Room')
            ->call('save')
            ->assertHasErrors(['type']);
    }

    public function test_resource_code_is_unique_across_resources(): void
    {
        Resource::factory()->ofType(ResourceType::Equipment)->create(['code' => 'EQP-DUP']);

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ResourceManager::class)
            ->call('newResource')
            ->set('type', 'equipment')
            ->set('code', 'EQP-DUP')
            ->set('name', 'Duplicate')
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_manager_excludes_rooms_from_the_list(): void
    {
        Room::factory()->create(['name' => 'Ruang Rahasia']);
        Resource::factory()->ofType(ResourceType::Desk)->create(['name' => 'Meja Hot Desk A']);

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ResourceManager::class)
            ->assertSee('Meja Hot Desk A')
            ->assertDontSee('Ruang Rahasia');
    }

    public function test_admin_can_toggle_a_resource_active_state(): void
    {
        $equipment = Resource::factory()->ofType(ResourceType::Equipment)->create(['is_active' => true]);

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ResourceManager::class)
            ->call('toggle', $equipment->id);

        $this->assertFalse($equipment->fresh()->is_active);
    }
}
