<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a session created from GET /api/punchout/setup/{secret} sends the
 * finished cart: see SessionManager::startFromSharedSecret(). That request
 * has no cXML body, so there is no per-session BrowserFormPostURL to
 * capture the way the real PunchOutSetupRequest POST provides one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_credentials', function (Blueprint $table): void {
            $table->text('browser_form_post_url')->nullable()->after('sender_identity');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_credentials', function (Blueprint $table): void {
            $table->dropColumn('browser_form_post_url');
        });
    }
};
