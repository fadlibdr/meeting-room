<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 A.1 — auto-release of no-show bookings.
 *
 * `released_at` is the no-show signal: a booking auto-cancelled by the
 * `bookings:auto-release` job because nobody checked in within the grace
 * window. Null = not auto-released. Stored in UTC like the other booking
 * timestamps. Distinct from cancelled_at (a manual cancel leaves released_at
 * null) so no-show analytics can isolate auto-releases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('released_at');
        });
    }
};
