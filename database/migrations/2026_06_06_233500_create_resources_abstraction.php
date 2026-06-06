<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 E (E1) — generalize `rooms` into a bookable-`resources` table.
 *
 * MySQL keeps every existing foreign key pointed at the renamed table, so
 * child columns (bookings.room_id, room_facility_items.room_id, …) continue
 * to reference it untouched. We add a `type` discriminator (default 'room',
 * so all existing rows stay rooms) and a per-type `attributes` JSON bag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('rooms', 'resources');

        Schema::table('resources', function (Blueprint $table) {
            $table->string('type', 32)->default('room')->after('id')->index();
            $table->json('metadata')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'metadata']);
        });

        Schema::rename('resources', 'rooms');
    }
};
