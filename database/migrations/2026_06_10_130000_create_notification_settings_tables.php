<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable notifications:
 *  - notification_channel_defaults: admin default per (type, channel) — whether
 *    it is enabled by default and whether users may override it.
 *  - notification_preferences: per-user override per (type, channel).
 *
 * Both are tenant-scoped (DB-default tenant_id, mirroring the 4a pattern).
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultId = DB::table('tenants')->where('is_default', true)->value('id') ?? 1;

        Schema::create('notification_channel_defaults', function (Blueprint $table) use ($defaultId) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default($defaultId)->index();
            $table->string('type', 50);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->boolean('user_overridable')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'type', 'channel']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) use ($defaultId) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default($defaultId)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'type', 'channel'], 'notif_pref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_channel_defaults');
    }
};
