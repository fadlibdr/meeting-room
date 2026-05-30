<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FacilityCategory;
use App\Livewire\Admin\FacilityForm;
use App\Models\Role;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FacilityFormCategoryTest extends TestCase
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

    public function test_form_accepts_every_category_defined_by_the_enum(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        foreach (FacilityCategory::cases() as $category) {
            Livewire::test(FacilityForm::class)
                ->set('code', 'cat-'.$category->value)
                ->set('name', 'Fasilitas '.$category->label())
                ->set('category', $category->value)
                ->call('save')
                ->assertHasNoErrors();
        }

        $this->assertSame(count(FacilityCategory::cases()), RoomFacility::count());
    }

    public function test_factory_only_produces_categories_the_enum_allows(): void
    {
        $allowed = FacilityCategory::values();

        foreach (RoomFacility::factory()->count(30)->create() as $facility) {
            $this->assertContains($facility->category, $allowed);
        }
    }
}
