<?php

declare(strict_types=1);

namespace Tests\Feature\Consent;

use App\Support\Consent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ConsentBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_essential_is_always_granted(): void
    {
        // No cookie at all.
        $this->assertTrue(Consent::granted('essential'));
    }

    public function test_analytics_denied_by_default(): void
    {
        // Privacy-preserving default: no consent cookie => analytics OFF.
        $this->assertFalse(Consent::granted('analytics'));
        $this->assertFalse(Consent::decided());
    }

    public function test_banner_shows_when_no_choice_made(): void
    {
        $this->get(route('legal.show', 'privacy'))
            ->assertOk()
            ->assertSee('Terima Semua', false);
    }

    public function test_banner_hidden_once_choice_is_made(): void
    {
        $this->withUnencryptedCookie(Consent::COOKIE, 'essential')
            ->get(route('legal.show', 'privacy'))
            ->assertOk()
            ->assertDontSee('Terima Semua', false);
    }

    public function test_accept_all_grants_analytics(): void
    {
        $this->app->instance('request', Request::create('/', 'GET', cookies: [Consent::COOKIE => 'all']));
        $this->assertTrue(Consent::granted('analytics'));
        $this->assertTrue(Consent::decided());
    }

    public function test_essential_only_choice_denies_analytics(): void
    {
        $this->app->instance('request', Request::create('/', 'GET', cookies: [Consent::COOKIE => 'essential']));
        $this->assertTrue(Consent::decided());
        $this->assertFalse(Consent::granted('analytics'));
        $this->assertTrue(Consent::granted('essential'));
    }
}
