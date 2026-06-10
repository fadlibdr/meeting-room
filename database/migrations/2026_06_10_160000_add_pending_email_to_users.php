<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email-change verification: a new email is staged in `pending_email` and only
 * applied once the user confirms via the link sent to that address. The current
 * email keeps working until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->string('pending_email_token', 64)->nullable()->unique()->after('pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_token']);
        });
    }
};
