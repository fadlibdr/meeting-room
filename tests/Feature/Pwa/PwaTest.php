<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_fallback_page_renders(): void
    {
        $this->get(route('offline'))
            ->assertOk()
            ->assertSee('offline')
            ->assertSee('Coba Lagi / Retry');
    }

    public function test_manifest_is_valid_and_installable(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#005490', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['icons']);

        // Must expose both a 192 and 512 icon (Chrome installability) and a
        // maskable variant, and every referenced icon file must exist.
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_service_worker_file_is_published(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_layout_links_the_manifest_and_theme_color(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('name="theme-color"', false);
    }
}
