<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_with_version_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('about'))
            ->assertOk()
            ->assertSee('v'.config('app.version'))
            ->assertSee(route('changelog'), false);
    }

    public function test_about_requires_authentication(): void
    {
        $this->get(route('about'))->assertRedirect(route('login'));
    }

    public function test_dropdown_links_about_and_release_notes_below_settings(): void
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee(route('about'), false)
            ->assertSee(route('changelog'), false);
    }
}
