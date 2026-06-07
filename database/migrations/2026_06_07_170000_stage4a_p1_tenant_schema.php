<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4a P1 — tenant_id across all tenant-owned tables + composite uniques.
 *
 * Strategy: tenant_id is NOT NULL with a DB DEFAULT of the "BPJS" default
 * tenant. This auto-backfills existing rows and means inserts work WITHOUT the
 * Eloquent trait — so the schema (P1) is decoupled from the trait/scoping
 * rollout (P2). Composite uniques become (tenant_id, …); with a single tenant
 * they behave exactly like the old global uniques, so the suite stays green.
 * FKs are deferred (index only) to keep this large migration reversible.
 *
 * Decisions (ADR-029): per-tenant roles; email unique per (tenant_id, email).
 */
return new class extends Migration
{
    /** Tables that GAIN a tenant_id column here (resources/bookings already have one). */
    private array $addTables = [
        'units', 'roles', 'role_permissions', 'user_roles', 'users',
        'room_facilities', 'room_facility_items', 'room_operating_hours', 'room_block_schedules',
        'booking_approvals', 'booking_attachments', 'booking_status_histories',
        'approval_policies', 'approval_policy_steps', 'approval_delegations',
        'activity_logs', 'app_settings', 'exports', 'notifications',
        'calendar_connections', 'booking_calendar_events',
        'webhook_subscriptions', 'webhook_deliveries',
    ];

    /** [table, old-unique-index-name, [composite columns]] */
    private array $composite = [
        ['users', 'users_email_unique', ['tenant_id', 'email']],
        ['users', 'users_employee_no_unique', ['tenant_id', 'employee_no']],
        ['units', 'units_code_unique', ['tenant_id', 'code']],
        ['roles', 'roles_code_unique', ['tenant_id', 'code']],
        ['resources', 'rooms_code_unique', ['tenant_id', 'code']],
        ['room_facilities', 'room_facilities_code_unique', ['tenant_id', 'code']],
        ['approval_policies', 'approval_policies_name_unique', ['tenant_id', 'name']],
        ['bookings', 'bookings_booking_code_unique', ['tenant_id', 'booking_code']],
        ['app_settings', 'app_settings_key_unique', ['tenant_id', 'key']],
    ];

    public function up(): void
    {
        // 1. Mark/ensure a default tenant.
        if (! Schema::hasColumn('tenants', 'is_default')) {
            Schema::table('tenants', function (Blueprint $t) {
                $t->boolean('is_default')->default(false)->after('status');
            });
        }
        $defaultId = DB::table('tenants')->where('is_default', true)->value('id');
        if ($defaultId === null) {
            $defaultId = DB::table('tenants')->insertGetId([
                'name' => 'BPJS Kesehatan', 'slug' => 'bpjs', 'status' => 'active',
                'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 2. Add tenant_id (NOT NULL, DB default → auto-backfills existing rows).
        foreach ($this->addTables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) use ($defaultId) {
                    $t->unsignedBigInteger('tenant_id')->default($defaultId);
                    $t->index('tenant_id');
                });
            }
        }

        // 3. resources/bookings already have a nullable tenant_id (P0): backfill + lock down.
        foreach (['resources', 'bookings'] as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultId]);
            Schema::table($table, function (Blueprint $t) use ($defaultId) {
                $t->unsignedBigInteger('tenant_id')->default($defaultId)->nullable(false)->change();
            });
        }

        // 4. Composite uniques.
        foreach ($this->composite as [$table, $oldIndex, $cols]) {
            Schema::table($table, function (Blueprint $t) use ($oldIndex, $cols) {
                $t->dropUnique($oldIndex);
                $t->unique($cols);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->composite as [$table, $oldIndex, $cols]) {
            Schema::table($table, function (Blueprint $t) use ($cols) {
                $t->dropUnique($cols);
            });
        }
        // Restore the original single-column uniques.
        $restore = [
            ['users', 'email'], ['users', 'employee_no'], ['units', 'code'], ['roles', 'code'],
            ['resources', 'code'], ['room_facilities', 'code'], ['approval_policies', 'name'],
            ['bookings', 'booking_code'], ['app_settings', 'key'],
        ];
        foreach ($restore as [$table, $col]) {
            Schema::table($table, fn (Blueprint $t) => $t->unique($col));
        }

        foreach ($this->addTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['tenant_id']);
                $t->dropColumn('tenant_id');
            });
        }
        // resources/bookings: revert to nullable (P0 state).
        foreach (['resources', 'bookings'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->change());
        }
    }
};
