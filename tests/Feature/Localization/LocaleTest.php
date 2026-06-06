<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_indonesian_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('lang="id"', false);
    }

    public function test_authenticated_user_locale_drives_the_ui_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="en"', false);
    }

    public function test_user_without_locale_falls_back_to_app_default(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="id"', false);
    }

    public function test_switching_locale_persists_for_authenticated_user(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->post(route('locale.update', 'en'))
            ->assertRedirect();

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_switching_locale_for_guest_uses_session(): void
    {
        $this->post(route('locale.update', 'en'))->assertRedirect();

        $this->get(route('login'))->assertSee('lang="en"', false);
    }

    public function test_unknown_locale_is_ignored(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->post(route('locale.update', 'fr'))
            ->assertRedirect();

        $this->assertSame('id', $user->refresh()->locale);
    }

    public function test_user_locale_takes_precedence_over_session(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->get(route('dashboard'))
            ->assertSee('lang="en"', false);
    }

    public function test_profile_form_saves_locale(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('locale', 'en')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_profile_form_rejects_invalid_locale(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', $user->email)
            ->set('locale', 'fr')
            ->call('updateProfileInformation')
            ->assertHasErrors('locale');

        $this->assertSame('id', $user->refresh()->locale);
    }
}
