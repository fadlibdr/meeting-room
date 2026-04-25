<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->index();
            $table->string('location', 150)->nullable()->index();
            $table->string('floor', 30)->nullable()->index();
            $table->unsignedSmallInteger('capacity')->default(1)->index();
            $table->string('status', 30)->default('active')->index();
            $table->string('approval_mode', 30)->default('unit_approver')->index();
            // Dec-10: post-meeting buffer only
            $table->unsignedSmallInteger('booking_buffer_minutes')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            // Dec-06: NO softDeletes()
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
