<?php

declare(strict_types=1);

namespace Tests\Feature\Changelog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_renders_publicly(): void
    {
        $this->get(route('changelog'))
            ->assertOk()
            ->assertSee('Catatan Rilis', false);
    }

    public function test_changelog_contains_a_known_release(): void
    {
        $this->get(route('changelog'))
            ->assertOk()
            ->assertSee('v1.51.0', false);
    }

    public function test_changelog_renders_markdown_as_html(): void
    {
        // The h1/h2 headings from CHANGELOG.md should be rendered, not shown raw.
        $this->get(route('changelog'))
            ->assertOk()
            ->assertSee('<h2', false)
            ->assertDontSee('## Stage 4', false);
    }
}
