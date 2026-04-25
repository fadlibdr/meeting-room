<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')
                ->constrained('rooms')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false)->index();
            $table->timestamps();

            $table->unique(['room_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_operating_hours');
    }
};
