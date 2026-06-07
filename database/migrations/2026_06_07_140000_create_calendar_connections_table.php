<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 F.2b/c — per-user delegated calendar connection (encrypted OAuth
 * tokens). Application/admin-consent mode does not use rows here; it derives
 * access from config + the target mailbox. One row per (user, provider).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // microsoft | google
            $table->text('access_token')->nullable();   // encrypted
            $table->text('refresh_token')->nullable();  // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('external_calendar_id')->nullable(); // null => primary
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_connections');
    }
};
