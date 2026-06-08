<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Go-to-market pages (Stage 4h.1) — SCAFFOLD ONLY.
 *
 * The whole surface 404s unless config('marketing.enabled') (default off):
 * structure now, copy + launch later. Pricing is gated separately
 * (marketing.pricing_enabled) and stays off until billing + legal + the
 * security review exist — so we never publish a commercial offering early.
 *
 * Content is editable Markdown at resources/marketing/<slug>.md.
 */
class MarketingController extends Controller
{
    public function show(string $page = 'landing'): View
    {
        abort_unless((bool) config('marketing.enabled', false), 404);

        $pages = (array) config('marketing.pages', []);
        abort_unless(array_key_exists($page, $pages), 404);

        // Pricing requires its own explicit flag (billing/legal/security gate).
        if ($page === 'pricing') {
            abort_unless((bool) config('marketing.pricing_enabled', false), 404);
        }

        $path = resource_path("marketing/{$page}.md");
        abort_unless(is_file($path), 404);

        return view('marketing.show', [
            'page' => $page,
            'title' => (string) $pages[$page],
            'html' => Str::markdown((string) file_get_contents($path)),
        ]);
    }
}
