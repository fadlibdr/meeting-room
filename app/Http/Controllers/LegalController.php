<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Serves the public trust/legal pages (Stage 4f.1).
 *
 * Content is editable Markdown at resources/legal/<slug>.md so legal/ops can
 * revise copy without a code change. Each page renders with a "draft — pending
 * legal review" banner until config('legal.reviewed.<slug>') is true.
 *
 * IMPORTANT: the shipped Markdown is a UU PDP (UU 27/2022) + GDPR-aware
 * STRUCTURE, not legal advice. The reviewed.* flags must stay false until
 * counsel has drafted and approved the binding text.
 */
class LegalController extends Controller
{
    public function show(string $doc): View
    {
        $documents = (array) config('legal.documents', []);
        abort_unless(array_key_exists($doc, $documents), 404);

        $path = resource_path("legal/{$doc}.md");
        abort_unless(is_file($path), 404);

        $markdown = (string) file_get_contents($path);

        return view('legal.show', [
            'slug' => $doc,
            'title' => (string) $documents[$doc],
            'html' => Str::markdown($markdown),
            'reviewed' => (bool) config("legal.reviewed.{$doc}", false),
        ]);
    }
}
