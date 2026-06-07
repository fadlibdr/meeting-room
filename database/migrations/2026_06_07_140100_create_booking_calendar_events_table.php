<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 F.2b/c — maps a booking to the external calendar event it created,
 * so later updates/cancellations target the right event (per provider, per
 * target mailbox).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('external_event_id');
            $table->timestamps();
            $table->unique(['booking_id', 'provider', 'target_user_id'], 'booking_cal_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_calendar_events');
    }
};
