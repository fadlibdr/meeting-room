<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Stream a completed export to its owner. Returns 404 (not 403) for
     * non-owners so an export's existence is not leaked, and for
     * pending/failed/expired/missing files.
     */
    public function download(Request $request, Export $export): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()?->id, 404);
        abort_unless($export->isDownloadable(), 404);

        $disk = Storage::disk(Export::DISK);
        abort_unless($export->path !== null && $disk->exists($export->path), 404);

        return $disk->download(
            $export->path,
            $export->filename ?? 'export.'.$export->format->extension(),
            ['Content-Type' => $export->format->mimeType()],
        );
    }
}
