<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2.2 — async/large data exports.
 *
 * Tracks user-requested exports that are too large to stream synchronously.
 * A queued job writes the file to the local_private disk and flips the row to
 * completed (with a notification); the download route streams it from `path`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('bookings');     // export subject
            $table->string('format', 8);                       // csv | xlsx
            $table->string('status', 16)->default('pending');  // ExportStatus
            $table->string('scope', 8)->default('own');        // own | all
            $table->json('filters')->nullable();               // serialized list filters
            $table->string('filename')->nullable();
            $table->string('path')->nullable();                // relative path on local_private
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
