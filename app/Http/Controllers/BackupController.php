<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function download(Request $request, DatabaseBackupService $backups)
    {
        abort_unless(
            $request->user()?->hasPermission('app-settings.update'),
            403,
        );

        $path = $backups->dumpToTempFile();
        $name = $backups->suggestedFilename();

        Log::warning('database_backup.downloaded', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'filename' => $name,
            'at' => now()->toIso8601String(),
        ]);

        return response()
            ->download($path, $name, [
                'Content-Type' => 'application/gzip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ])
            ->deleteFileAfterSend(true);
    }
}
