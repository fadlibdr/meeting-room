<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4a P0 spike — tenant_id on the resources + bookings vertical only.
 * Nullable (unused while tenancy is off). Full-table rollout + composite unique
 * keys + backfill are P1, not this spike.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['resources', 'bookings'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['resources', 'bookings'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
