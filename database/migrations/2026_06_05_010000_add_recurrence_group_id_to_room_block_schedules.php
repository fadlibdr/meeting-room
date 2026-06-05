<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring room blocks: link every occurrence of a repeating block to the
 * first block in its series (self-referential, mirroring bookings.recurrence_group_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_block_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('recurrence_group_id')->nullable()->after('room_id');
            $table->index(['recurrence_group_id'], 'idx_rbs_recurrence_group');
            $table->foreign('recurrence_group_id')
                ->references('id')->on('room_block_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_block_schedules', function (Blueprint $table) {
            $table->dropForeign(['recurrence_group_id']);
            $table->dropIndex('idx_rbs_recurrence_group');
            $table->dropColumn('recurrence_group_id');
        });
    }
};
