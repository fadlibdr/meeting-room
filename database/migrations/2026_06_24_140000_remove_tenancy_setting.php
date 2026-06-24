<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Multi-tenancy rollback: drop the now-defunct system.tenancy_enabled app
 * setting. The tenant_id columns and tenants table are intentionally LEFT in
 * place (reversible — no data destroyed).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->where('key', 'system.tenancy_enabled')->delete();
    }

    public function down(): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'system.tenancy_enabled'],
            [
                'value' => '0',
                'data_type' => 'boolean',
                'label' => 'Mode Multi-Penyewa (Tenancy)',
                'description' => 'Mengaktifkan isolasi data multi-penyewa.',
                'group' => 'system',
                'is_editable' => true,
            ],
        );
    }
};
