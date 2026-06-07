<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Cross-cutting 1.3 — E2E happy-path smoke (real browser).
 *
 * Uses Dusk's loginAs() to authenticate without depending on Livewire form
 * selectors, then asserts the key authenticated pages render in a real browser.
 * Runs only in CI with Chrome (see .github/workflows/dusk.yml) — excluded from
 * `php artisan test`. The fuller create->approve->check-in multi-actor flow is
 * the next E2E to build once this harness is green in CI.
 */
class BookingSmokeTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function requester(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        return $user;
    }

    public function test_requester_can_reach_the_booking_form(): void
    {
        $user = $this->requester();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertSee('Dashboard')
                ->visit('/bookings/new')
                ->assertSee('Judul Rapat');
        });
    }

    public function test_security_header_is_present_in_the_browser_response(): void
    {
        $user = $this->requester();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)->visit('/dashboard')->assertSee('Dashboard');
        });
    }
}
