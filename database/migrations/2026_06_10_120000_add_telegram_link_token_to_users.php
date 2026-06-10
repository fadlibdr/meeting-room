<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time deep-link token used to associate a Telegram chat with a user when
 * they press "Hubungkan Telegram" (t.me/<bot>?start=<token>); cleared once the
 * /start webhook captures their chat id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_link_token');
        });
    }
};
