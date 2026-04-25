<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_facility_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')
                ->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('room_facility_id')
                ->constrained('room_facilities')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('is_operational')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'room_facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_facility_items');
    }
};
