<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Tests for custom Blade directives registered in BladeDirectivesProvider.
 *
 * Per Blueprint Dec-09: @displayDateTime and @displayDate convert UTC values
 * to user's timezone (auth()->user()->timezone), falling back to
 * config('app.display_timezone'), then to a hardcoded 'Asia/Jakarta'.
 */
class BladeDirectivesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function requesterUser(?string $timezone = null): User
    {
        $user = User::factory()->create(['timezone' => $timezone]);
        $role = Role::where('code', 'requester')->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    // ─── @displayDateTime ──────────────────────────────────────────────

    public function test_display_date_time_uses_user_timezone_when_set(): void
    {
        $user = $this->requesterUser('Asia/Jakarta');
        $this->actingAs($user);

        // 10:00 UTC → 17:00 WIB
        $output = Blade::render(
            "@displayDateTime('2026-05-11T10:00:00+00:00')"
        );

        $this->assertStringContainsString('11 May 2026', $output);
        $this->assertStringContainsString('17:00', $output);
        $this->assertStringContainsString('WIB', $output);
    }

    public function test_display_date_time_renders_non_jakarta_timezone_with_raw_label(): void
    {
        $user = $this->requesterUser('Asia/Makassar'); // WITA (UTC+8)
        $this->actingAs($user);

        // 10:00 UTC → 18:00 WITA
        $output = Blade::render(
            "@displayDateTime('2026-05-11T10:00:00+00:00')"
        );

        $this->assertStringContainsString('18:00', $output);
        // Per directive code: only Asia/Jakarta gets the 'WIB' label;
        // other timezones echo the raw IANA identifier.
        $this->assertStringContainsString('Asia/Makassar', $output);
        $this->assertStringNotContainsString('WIB', $output);
    }

    public function test_display_date_time_falls_back_to_config_when_user_timezone_null(): void
    {
        Config::set('app.display_timezone', 'Asia/Jakarta');
        $user = $this->requesterUser(null);
        $this->actingAs($user);

        // 10:00 UTC → 17:00 WIB (via config fallback)
        $output = Blade::render(
            "@displayDateTime('2026-05-11T10:00:00+00:00')"
        );

        $this->assertStringContainsString('17:00', $output);
        $this->assertStringContainsString('WIB', $output);
    }

    public function test_display_date_time_falls_back_to_config_when_unauthenticated(): void
    {
        Config::set('app.display_timezone', 'Asia/Jakarta');
        // No actingAs() — unauthenticated state

        $output = Blade::render(
            "@displayDateTime('2026-05-11T10:00:00+00:00')"
        );

        $this->assertStringContainsString('17:00', $output);
        $this->assertStringContainsString('WIB', $output);
    }

    public function test_display_date_time_renders_nothing_for_null_value(): void
    {
        $user = $this->requesterUser('Asia/Jakarta');
        $this->actingAs($user);

        $output = Blade::render('@displayDateTime(null)');

        // Directive guards `if ($__dt)` — null/empty produces no output.
        $this->assertSame('', trim($output));
    }

    // ─── @displayDate ──────────────────────────────────────────────────

    public function test_display_date_renders_user_timezone_date_only(): void
    {
        $user = $this->requesterUser('Asia/Jakarta');
        $this->actingAs($user);

        $output = Blade::render(
            "@displayDate('2026-05-11T17:00:00+00:00')"
        );

        // 17:00 UTC = 00:00 next-day WIB → date should be 12 May, not 11 May
        $this->assertStringContainsString('12 May 2026', $output);
        // No time component — date directive omits H:i
        $this->assertStringNotContainsString(':', $output);
    }

    public function test_display_date_falls_back_to_config_when_user_timezone_null(): void
    {
        Config::set('app.display_timezone', 'Asia/Jakarta');
        $user = $this->requesterUser(null);
        $this->actingAs($user);

        $output = Blade::render(
            "@displayDate('2026-05-11T10:00:00+00:00')"
        );

        $this->assertStringContainsString('11 May 2026', $output);
    }
}
