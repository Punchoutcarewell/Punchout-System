<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            // Set only by SessionManager::startPreview(), the Admin
            // "generate a test token" tool: distinguishes an admin's own
            // storefront preview from a session a real Coupa request
            // produced, so nothing downstream (reporting, reconciliation)
            // has to guess from buyer_business_unit string matching.
            $table->boolean('is_preview')->default(false)->after('operation');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            $table->dropColumn('is_preview');
        });
    }
};
