<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('bookings')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_no')->default(1)->index();
            $table->foreignId('approver_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('action_at')->nullable()->index();
            $table->text('action_notes')->nullable();
            $table->foreignId('acted_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_approvals');
    }
};
