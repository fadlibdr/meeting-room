<?php

namespace App\Enums;

enum NotificationType: string
{
    case BookingSubmitted = 'booking_submitted';
    case BookingApproved = 'booking_approved';
    case BookingRejected = 'booking_rejected';
    case BookingCancelled = 'booking_cancelled';
    case BookingReminder = 'booking_reminder';
    case RoomBlockCreated = 'room_block_created';
    case ExportReady = 'export_ready';
    case BookingAutoReleased = 'booking_auto_released';
}
