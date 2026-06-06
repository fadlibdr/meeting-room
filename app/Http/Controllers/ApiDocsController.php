<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the public API's OpenAPI spec and a browsable docs page (Stage 3 C).
 *
 * The spec lives at docs/openapi-v1.yaml; the page renders it with a locally
 * vendored Redoc bundle (no CDN — works on the intranet). Auth-gated.
 */
class ApiDocsController extends Controller
{
    private const SPEC_PATH = 'docs/openapi-v1.yaml';

    public function page(): Response
    {
        return response()->view('api-docs');
    }

    public function spec(): Response
    {
        $path = base_path(self::SPEC_PATH);
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
