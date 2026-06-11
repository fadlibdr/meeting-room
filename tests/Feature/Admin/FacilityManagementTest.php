<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\FacilityForm;
use App\Livewire\Admin\FacilityList;
use App\Models\Role;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FacilityManagementTest extends TestCase
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

    public function test_super_admin_can_view_facility_index(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get(route('admin.facilities.index'))->assertOk();
    }

    public function test_ga_admin_can_view_facility_index(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'))->get(route('admin.facilities.index'))->assertOk();
    }

    public function test_requester_cannot_view_facility_index(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.facilities.index'))->assertForbidden();
    }

    public function test_unit_approver_cannot_view_facility_index(): void
    {
        $this->actingAs($this->userWithRole('unit_approver'))->get(route('admin.facilities.index'))->assertForbidden();
    }

    public function test_admin_can_view_create_and_edit_screens(): void
    {
        $facility = RoomFacility::factory()->create();
        $admin = $this->userWithRole('ga_admin');
        $this->actingAs($admin)->get(route('admin.facilities.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.facilities.edit', $facility))->assertOk();
    }

    public function test_requester_cannot_view_create_screen(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.facilities.create'))->assertForbidden();
    }

    public function test_admin_can_create_facility(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(FacilityForm::class)
            ->set('code', 'projector')
            ->set('name', 'Proyektor')
            ->set('category', 'av')
            ->set('icon', 'projector-icon')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'));

        $facility = RoomFacility::where('code', 'projector')->first();
        $this->assertNotNull($facility);
        $this->assertSame('Proyektor', $facility->name);
        $this->assertSame('av', $facility->category);
        $this->assertTrue($facility->is_active);
    }

    public function test_facility_requires_code_and_name(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(FacilityForm::class)
            ->set('code', '')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['code', 'name']);
    }

    public function test_facility_code_must_be_unique(): void
    {
        RoomFacility::factory()->create(['code' => 'projector']);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(FacilityForm::class)
            ->set('code', 'projector')
            ->set('name', 'Proyektor Lain')
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(FacilityForm::class)
            ->set('code', 'gadget')
            ->set('name', 'Gadget')
            ->set('category', 'nonsense')
            ->call('save')
            ->assertHasErrors(['category']);
    }

    public function test_admin_can_update_facility(): void
    {
        $facility = RoomFacility::factory()->create(['name' => 'Nama Lama']);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(FacilityForm::class, ['facility' => $facility])
            ->assertSet('isEditMode', true)
            ->assertSet('name', 'Nama Lama')
            ->set('name', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'));

        $facility->refresh();
        $this->assertSame('Nama Baru', $facility->name);
    }

    public function test_admin_can_toggle_facility_active(): void
    {
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(FacilityList::class)->call('toggleActive', $facility->id);
        $facility->refresh();
        $this->assertFalse($facility->is_active);

        Livewire::test(FacilityList::class)->call('toggleActive', $facility->id);
        $facility->refresh();
        $this->assertTrue($facility->is_active);
    }

    public function test_requester_cannot_toggle_facility(): void
    {
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(FacilityList::class)->call('toggleActive', $facility->id)->assertForbidden();

        $facility->refresh();
        $this->assertTrue($facility->is_active);
    }
}
