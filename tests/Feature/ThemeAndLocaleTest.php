<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_navbar_has_theme_toggle_and_locale_switcher(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // Theme toggle (persists to localStorage) is present.
        $response->assertSee('theme-toggle', false);
        $response->assertSee("localStorage.setItem('theme'", false);
        // No-FOUC head init.
        $response->assertSee("classList.add('dark')", false);
        // Locale switcher present in the chrome.
        $response->assertSee(route('locale.update', 'id'), false);
    }
}
