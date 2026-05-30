<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->nullable()
                ->constrained('units')->nullOnDelete();
            $table->string('employee_no', 50)->nullable()->unique();
            $table->string('name', 150)->index();
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('job_title', 150)->nullable();
            // FK added later in add_self_references migration
            $table->unsignedBigInteger('approver_user_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            // Dec-09: timezone support
            $table->string('timezone', 50)->nullable();
            $table->timestamp('last_login_at')->nullable()->index();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            // Dec-06: NO softDeletes()
        });

        // Sessions table (SESSION_DRIVER=database)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Password resets (Laravel native)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
