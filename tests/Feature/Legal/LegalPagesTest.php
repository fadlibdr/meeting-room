<?php

declare(strict_types=1);

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function documentProvider(): array
    {
        return [
            'terms' => ['terms'],
            'privacy' => ['privacy'],
            'dpa' => ['dpa'],
            'security' => ['security'],
        ];
    }

    #[DataProvider('documentProvider')]
    public function test_each_legal_page_renders(string $doc): void
    {
        $this->get(route('legal.show', $doc))->assertOk();
    }

    public function test_unknown_document_404s(): void
    {
        $this->get(route('legal.show', 'cookies'))->assertNotFound();
    }

    public function test_draft_banner_shows_when_not_reviewed(): void
    {
        config(['legal.reviewed.terms' => false]);

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertSee('menunggu peninjauan hukum', false);
    }

    public function test_draft_banner_hidden_when_reviewed(): void
    {
        config(['legal.reviewed.terms' => true]);

        $this->get(route('legal.show', 'terms'))
            ->assertOk()
            ->assertDontSee('menunggu peninjauan hukum', false);
    }

    public function test_security_page_is_truthful_about_gaps(): void
    {
        // The security page must keep listing what is NOT done — never silently drop it.
        $this->get(route('legal.show', 'security'))
            ->assertOk()
            ->assertSee('Belum ada uji penetrasi', false);
    }
}
