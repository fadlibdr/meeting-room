<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\UnitForm;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_admin_can_create_a_unit(): void
    {
        Livewire::actingAs($this->admin())
            ->test(UnitForm::class)
            ->set('code', 'BIRO-UMUM')
            ->set('name', 'Biro Umum')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', ['code' => 'BIRO-UMUM', 'name' => 'Biro Umum']);
    }

    public function test_admin_can_create_a_subunit_with_a_parent(): void
    {
        $parent = Unit::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(UnitForm::class)
            ->set('code', 'SUB-1')
            ->set('name', 'Sub Unit')
            ->set('parentId', $parent->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', ['code' => 'SUB-1', 'parent_id' => $parent->id]);
    }

    public function test_code_must_be_unique(): void
    {
        Unit::factory()->create(['code' => 'DUP']);

        Livewire::actingAs($this->admin())
            ->test(UnitForm::class)
            ->set('code', 'DUP')
            ->set('name', 'Another')
            ->call('save')
            ->assertHasErrors('code');
    }

    public function test_unit_cannot_be_its_own_parent(): void
    {
        $unit = Unit::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(UnitForm::class, ['unit' => $unit])
            ->set('parentId', $unit->id)
            ->call('save')
            ->assertHasErrors('parentId');
    }

    public function test_a_descendant_cannot_become_the_parent(): void
    {
        $parent = Unit::factory()->create();
        $child = Unit::factory()->create(['parent_id' => $parent->id]);

        // Making the parent a child of its own descendant would create a cycle.
        Livewire::actingAs($this->admin())
            ->test(UnitForm::class, ['unit' => $parent])
            ->set('parentId', $child->id)
            ->call('save')
            ->assertHasErrors('parentId');
    }
}
