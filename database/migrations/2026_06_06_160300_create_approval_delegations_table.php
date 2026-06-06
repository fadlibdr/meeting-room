<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 B — approval delegations.
 *
 * While a delegation is active (starts_at <= now <= ends_at, or ends_at NULL =
 * open-ended), any approval step that resolves to `from_user_id` is re-routed
 * to `to_user_id`, so an away approver's queue flows to a stand-in. One hop
 * only (delegations do not chain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['from_user_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
