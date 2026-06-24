<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt bearer tokens at rest (SOC 2 CC6.1 / ISO 27001 A.8.24). The token
 * columns become encrypted (model cast) so a DB/backup leak can't yield usable
 * tokens; an indexed SHA-256 hash column preserves O(1) lookup. Existing
 * plaintext tokens are cleared (the encrypted cast can't read them) — affected
 * users simply re-subscribe / re-link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted payloads are far longer than the plaintext tokens were —
            // widen the columns to hold the ciphertext.
            $table->text('calendar_feed_token')->nullable()->change();
            $table->text('telegram_link_token')->nullable()->change();

            $table->string('calendar_feed_token_hash', 64)->nullable()->after('calendar_feed_token')->index();
            $table->string('telegram_link_token_hash', 64)->nullable()->after('telegram_link_token')->index();
        });

        // Clear pre-existing plaintext tokens — they can't be decrypted by the
        // new cast, so users regenerate them on next use.
        DB::table('users')->update([
            'calendar_feed_token' => null,
            'telegram_link_token' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['calendar_feed_token_hash', 'telegram_link_token_hash']);
        });
    }
};
