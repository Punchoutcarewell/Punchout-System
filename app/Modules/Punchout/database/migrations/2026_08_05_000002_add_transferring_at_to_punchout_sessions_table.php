<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports a Transferring state that still resolves for a short grace
 * window (H6/H7): the browser's form post to Coupa's browserFormPostUrl
 * is fire-and-forget from this app's side, there is no callback in the
 * cXML PunchOut protocol confirming Coupa actually received it. Marking
 * the session Transferred the instant the page renders meant a network
 * blip, a blocked script, or the buyer closing the tab left no way back:
 * the session was already dead by the time the browser even tried to
 * submit the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            $table->timestamp('transferring_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            $table->dropColumn('transferring_at');
        });
    }
};
