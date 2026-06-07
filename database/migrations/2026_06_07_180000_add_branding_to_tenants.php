<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stage 4 (4b) — per-tenant white-label branding + a feature-flag bag (4e).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('name');
            $table->string('brand_color', 9)->nullable()->after('brand_name');   // hex #RRGGBB
            $table->string('logo_url')->nullable()->after('brand_color');
            $table->string('email_from_name')->nullable()->after('logo_url');
            $table->string('email_from_address')->nullable()->after('email_from_name');
            $table->json('features')->nullable()->after('email_from_address');    // 4e feature flags
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'brand_color', 'logo_url', 'email_from_name', 'email_from_address', 'features']);
        });
    }
};
