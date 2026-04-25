<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_block_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')
                ->constrained('rooms')->cascadeOnDelete();
            $table->string('block_type', 30)->default('maintenance')->index();
            $table->string('title', 150)->index();
            $table->text('reason')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->foreignId('created_by_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->foreignId('cancelled_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(
                ['room_id', 'is_active', 'starts_at', 'ends_at'],
                'idx_rbs_room_active_timeline'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_block_schedules');
    }
};
