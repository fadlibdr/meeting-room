<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UserForm;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $u;
    }

    public function test_admin_can_set_a_specific_password(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->set('newPassword', 'BrandNew123')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertSet('resetResult', 'BrandNew123');

        $this->assertTrue(Hash::check('BrandNew123', $target->fresh()->password));
    }

    public function test_admin_reset_generates_a_password_when_blank(): void
    {
        $target = User::factory()->create();
        $old = $target->password;

        $component = Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->set('newPassword', '')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $generated = $component->get('resetResult');
        $this->assertNotEmpty($generated);
        $this->assertNotSame($old, $target->fresh()->password);
        $this->assertTrue(Hash::check($generated, $target->fresh()->password));
    }

    public function test_short_password_is_rejected(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->set('newPassword', 'short')
            ->call('resetPassword')
            ->assertHasErrors(['newPassword']);
    }

    public function test_non_admin_cannot_reset(): void
    {
        $requester = User::factory()->create();
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);
        $target = User::factory()->create();

        // A non-admin can't even mount the form now (authorized in mount()), so
        // the reset action is unreachable — assert the mount-level block.
        Livewire::actingAs($requester)
            ->test(UserForm::class, ['user' => $target])
            ->assertForbidden();
    }

    public function test_profile_no_longer_shows_self_delete(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('profile'))
            ->assertOk()
            ->assertDontSee('Hapus Akun', false);
    }

    public function test_user_list_no_longer_shows_anonymize_button(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('>Anonimkan<', false);
    }
}
