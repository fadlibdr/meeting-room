<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagged_user_is_redirected_to_the_change_page(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => true]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('password.change-required'));
    }

    public function test_change_page_itself_is_reachable_while_flagged(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => true]);

        $this->actingAs($user)->get(route('password.change-required'))->assertOk();
    }

    public function test_setting_a_new_password_clears_the_flag(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => true]);

        Volt::actingAs($user)
            ->test('auth.force-password-change')
            ->set('password', 'NewStrongPass123')
            ->set('password_confirmation', 'NewStrongPass123')
            ->call('update')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewStrongPass123', $user->password));
    }

    public function test_unflagged_user_is_not_redirected(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
