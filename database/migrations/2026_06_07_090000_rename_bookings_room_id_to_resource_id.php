<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 E3 — rename bookings.room_id to resource_id.
 *
 * The column has held any resource id since E2; this makes the name honest.
 * MySQL carries the existing foreign key over to the renamed column. Only the
 * bookings link is renamed — the room-specific child tables (facilities,
 * operating hours, blocks) keep their room_id, as they remain room concepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('room_id', 'resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('resource_id', 'room_id');
        });
    }
};
