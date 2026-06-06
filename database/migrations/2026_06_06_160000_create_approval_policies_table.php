<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 B — configurable multi-step approval policies.
 *
 * A named, reusable approval chain assignable to rooms and/or units. The
 * resolution order is room policy > requester-unit policy > the legacy
 * per-room approval_mode, so existing single-step behaviour is preserved when
 * no policy is assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policies');
    }
};
