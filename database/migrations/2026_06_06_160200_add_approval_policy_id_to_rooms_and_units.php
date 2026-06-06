<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 B — assign an approval policy to a room and/or a unit.
 *
 * Null = no policy (fall back to the legacy per-room approval_mode). Resolution
 * order at submit time: room policy > requester-unit policy > approval_mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('approval_policy_id')->nullable()->after('approval_mode')
                ->constrained('approval_policies')->nullOnDelete();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('approval_policy_id')->nullable()
                ->constrained('approval_policies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_policy_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_policy_id');
        });
    }
};
