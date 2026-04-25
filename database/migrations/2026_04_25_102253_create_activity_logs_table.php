<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('module', 50)->index();
            $table->string('event', 50)->index();
            $table->string('subject_type', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['module', 'event', 'created_at'], 'idx_al_mec');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_al_subject');
            $table->index(['actor_user_id', 'created_at'], 'idx_al_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
