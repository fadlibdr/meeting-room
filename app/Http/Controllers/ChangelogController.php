<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Serves the public changelog (Stage 4g.2), rendered from the maintained
 * CHANGELOG.md at the repo root — same Markdown-page pattern as the legal
 * pages. Edit CHANGELOG.md to update; no code change needed.
 */
class ChangelogController extends Controller
{
    public function show(): View
    {
        $path = base_path('CHANGELOG.md');
        $markdown = is_file($path) ? (string) file_get_contents($path) : '# Catatan Rilis';

        return view('changelog', [
            'html' => Str::markdown($markdown),
        ]);
    }
}
