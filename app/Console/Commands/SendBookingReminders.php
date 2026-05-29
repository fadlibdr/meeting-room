<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use Illuminate\Console\Command;

/**
 * Sends a one-time in-app reminder to the requester of each Approved booking
 * that starts within the lead-time window and has not been reminded yet.
 * Stamps reminder_sent_at so a re-run (or the hourly schedule) never re-sends.
 *
 * Scheduled hourly in routes/console.php.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send in-app reminders for approved bookings starting within the reminder window.';

    private const REMINDER_LEAD_HOURS = 24;

    public function handle(): int
    {
        $bookings = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours(self::REMINDER_LEAD_HOURS))
            ->with('requester')
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            $requester = $booking->requester;

            if (! $requester instanceof User) {
                continue;
            }

            $requester->notify(new BookingReminderNotification($booking));
            $booking->update(['reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} booking reminder(s).");

        return self::SUCCESS;
    }
}
