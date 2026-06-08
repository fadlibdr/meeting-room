<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Public status page (Stage 4g.3). Shows ONLY an overall up/degraded/down
 * badge — never internal component detail (consistent with the shallow /up
 * decision; depth stays admin-only in system:health-check).
 *
 * The computed status is cached briefly so public traffic doesn't re-run the
 * checks on every hit (and so response timing can't be probed for detail).
 */
class StatusController extends Controller
{
    private const CACHE_KEY = 'public_status.summary';

    private const CACHE_TTL_SECONDS = 30;

    public function show(SystemHealthService $health): View
    {
        $status = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            static fn (): string => $health->status(),
        );

        return view('status', ['status' => $status]);
    }
}
