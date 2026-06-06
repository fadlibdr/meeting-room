<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 B — ordered steps of an approval policy.
 *
 * Each step resolves to ONE approver at submit time:
 *   - 'unit_approver': the requester's own approver (per-requester routing)
 *   - 'role': any active holder of role_id
 *   - 'user': the specific approver_user_id
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_policy_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_policy_id')->constrained('approval_policies')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_no');
            $table->string('approver_type', 20); // unit_approver | role | user
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['approval_policy_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policy_steps');
    }
};
