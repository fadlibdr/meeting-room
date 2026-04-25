<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking Rules
    |--------------------------------------------------------------------------
    |
    | Per Blueprint v3 §B (Reconciliation Decisions).
    |
    */

    // Dec-11: Maximum booking duration in hours.
    // Validated in Form Request + Service layer.
    'max_booking_duration_hours' => env('MEETING_ROOM_MAX_DURATION_HOURS', 8),

    // Dec-10: Default post-meeting buffer (minutes) for new rooms.
    // Individual rooms override via rooms.booking_buffer_minutes.
    'default_buffer_minutes' => env('MEETING_ROOM_DEFAULT_BUFFER', 0),

    // Dec-12: Draft bookings older than this are auto-purged.
    'draft_purge_after_days' => env('MEETING_ROOM_DRAFT_PURGE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'activity_log_retention_days' => env('MEETING_ROOM_ACTIVITY_RETENTION', 365),
    'notification_read_retention_days' => env('MEETING_ROOM_NOTIF_RETENTION', 90),

    /*
    |--------------------------------------------------------------------------
    | Booking Code Format
    |--------------------------------------------------------------------------
    |
    | Per Database Schema v2 §D (bookings.booking_code).
    | Format: BKG-YYYYMMDD-XXXX
    |
    */

    'booking_code_prefix' => 'BKG',

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachment_max_size_kb' => env('MEETING_ROOM_ATTACHMENT_MAX_KB', 10240), // 10 MB
    'attachment_allowed_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'],
    'attachment_disk' => 'local_private',

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    */

    'export_sync_threshold_rows' => env('MEETING_ROOM_EXPORT_SYNC_THRESHOLD', 10000),
];
