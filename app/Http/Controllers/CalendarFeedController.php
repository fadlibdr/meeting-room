<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\IcsGenerator;
use App\Services\SettingsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Stage 3 F.2a — public, tokened .ics subscription feed.
 *
 * The per-user token in the URL is the only credential (no session), so calendar
 * apps (Outlook/Google/Apple) can poll it on a schedule. Returns the user's
 * active (submitted/approved) bookings from the recent past onward.
 */
class CalendarFeedController extends Controller
{
    public function feed(string $token, IcsGenerator $ics, TenantContext $tenant): Response
    {
        // Configurable policy: the external .ics feed can be disabled org-wide.
        abort_unless((bool) app(SettingsService::class)->get('security.calendar_feed_enabled', true), 404);

        // The token IS the credential but is stored encrypted at rest — resolve it
        // across tenants by its lookup hash, then pin the context so the bookings
        // query is tenant-scoped.
        $user = User::query()->withoutGlobalScope('tenant')
            ->where('calendar_feed_token_hash', User::hashToken($token))
            ->first();

        abort_if($user === null, 404);

        $bookings = $tenant->runFor((int) $user->tenant_id, fn () => Booking::query()
            ->where('requester_user_id', $user->id)
            ->whereIn('status', [BookingStatus::Submitted->value, BookingStatus::Approved->value])
            ->where('ends_at', '>=', Carbon::now()->subWeek())
            ->with('resource:id,name,location')
            ->orderBy('starts_at')
            ->get());

        $body = $ics->forFeed($bookings, 'Reservasi BPJS — '.$user->name);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="bpjs-reservasi.ics"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
