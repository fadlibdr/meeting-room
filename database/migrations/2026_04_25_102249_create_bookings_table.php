<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 40)->unique();
            $table->foreignId('room_id')
                ->constrained('rooms')->restrictOnDelete();
            $table->foreignId('requester_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('requester_unit_id')->nullable()
                ->constrained('units')->nullOnDelete();
            $table->foreignId('created_by_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('subject', 150)->index();
            $table->text('agenda')->nullable();
            $table->unsignedSmallInteger('attendee_count')->default(1);
            // Dec-09: UTC datetime
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('source', 20)->default('user')->index();
            // Dec-04: string snapshot, NOT boolean
            $table->string('approval_mode_snapshot', 30)->default('unit_approver')->index();
            // Dec-03: hybrid pointer (step + user_id)
            $table->unsignedTinyInteger('current_approval_step')->nullable()->index();
            $table->foreignId('current_approver_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            // Dec-07: link to old booking (FK added in add_self_references)
            $table->unsignedBigInteger('rescheduled_from_booking_id')->nullable()->index();
            // Dec-13: placeholder recurrence (FK added in add_self_references)
            $table->unsignedBigInteger('recurrence_group_id')->nullable()->index();
            $table->timestamps();

            // Composite indexes per Database Schema v2 §H.1
            $table->index(['room_id', 'status', 'starts_at'], 'idx_bk_room_status_start');
            $table->index(['room_id', 'starts_at', 'ends_at'], 'idx_bk_room_timeline');
            $table->index(['requester_user_id', 'status', 'starts_at'], 'idx_bk_requester');
            $table->index(['current_approver_user_id', 'status'], 'idx_bk_approver_queue');
            $table->index(['status', 'submitted_at'], 'idx_bk_status_submitted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
