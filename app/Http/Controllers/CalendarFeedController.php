<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\IcsGenerator;
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
    public function feed(string $token, IcsGenerator $ics): Response
    {
        $user = User::query()->where('calendar_feed_token', $token)->first();

        abort_if($user === null, 404);

        $bookings = Booking::query()
            ->where('requester_user_id', $user->id)
            ->whereIn('status', [BookingStatus::Submitted->value, BookingStatus::Approved->value])
            ->where('ends_at', '>=', Carbon::now()->subWeek())
            ->with('resource:id,name,location')
            ->orderBy('starts_at')
            ->get();

        $body = $ics->forFeed($bookings, 'Reservasi BPJS — '.$user->name);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="bpjs-reservasi.ics"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
