<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ResourceManager;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage post-4 — disambiguation between bookable Resources and non-bookable
 * Facilities (amenities). The two decision points must carry the guidance that
 * distinguishes them by schedulability.
 */
class ResourceFacilityGuidanceTest extends TestCase
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
        $user->roles()->sync([Role::where('code', 'ga_admin')->firstOrFail()->id]);

        return $user;
    }

    public function test_facilities_page_explains_facilities_are_not_bookable(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('tidak memiliki jadwal sendiri', false)
            ->assertSee('Sumber Daya', false);
    }

    public function test_resource_form_explains_when_to_use_a_facility_instead(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ResourceManager::class)
            ->call('newResource')
            ->assertSet('showForm', true)
            ->assertSee('Sumber Daya vs Fasilitas', false)
            ->assertSee('daftarkan sebagai Fasilitas', false);
    }
}
