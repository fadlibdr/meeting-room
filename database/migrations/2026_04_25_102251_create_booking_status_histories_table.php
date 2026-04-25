<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('bookings')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable()->index();
            $table->string('to_status', 30)->index();
            $table->foreignId('changed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamp('changed_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
    }
};
