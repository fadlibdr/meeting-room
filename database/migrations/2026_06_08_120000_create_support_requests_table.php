<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4g.1 — in-app support / contact requests.
 *
 * tenant_id follows the established DB-default pattern (NOT NULL, default →
 * the BPJS default tenant) so BelongsToTenant works and the flag-off path is a
 * no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultId = DB::table('tenants')->where('is_default', true)->value('id') ?? 1;

        Schema::create('support_requests', function (Blueprint $table) use ($defaultId) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default($defaultId)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('subject', 150)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
