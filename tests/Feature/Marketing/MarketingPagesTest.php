<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_404s_while_disabled(): void
    {
        config(['marketing.enabled' => false]);

        $this->get(route('marketing.show', 'landing'))->assertNotFound();
        $this->get(route('marketing.show'))->assertNotFound(); // default landing
    }

    public function test_landing_and_features_render_when_enabled(): void
    {
        config(['marketing.enabled' => true]);

        $this->get(route('marketing.show'))->assertOk()->assertSee('Pemesanan Ruang Rapat', false);
        $this->get(route('marketing.show', 'features'))->assertOk()->assertSee('Fitur', false);
    }

    public function test_draft_preview_banner_is_shown(): void
    {
        config(['marketing.enabled' => true]);

        $this->get(route('marketing.show'))
            ->assertOk()
            ->assertSee('belum diluncurkan', false);
    }

    public function test_pricing_stays_404_even_when_marketing_enabled(): void
    {
        config(['marketing.enabled' => true, 'marketing.pricing_enabled' => false]);

        $this->get(route('marketing.show', 'pricing'))->assertNotFound();
    }

    public function test_pricing_renders_only_with_its_own_flag(): void
    {
        config(['marketing.enabled' => true, 'marketing.pricing_enabled' => true]);

        $this->get(route('marketing.show', 'pricing'))->assertOk();
    }

    public function test_unknown_page_404s(): void
    {
        config(['marketing.enabled' => true]);

        $this->get(route('marketing.show', 'careers'))->assertNotFound();
    }
}
