<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('approver_user_id')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('rescheduled_from_booking_id')
                ->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('recurrence_group_id')
                ->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approver_user_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_from_booking_id']);
            $table->dropForeign(['recurrence_group_id']);
        });
    }
};
