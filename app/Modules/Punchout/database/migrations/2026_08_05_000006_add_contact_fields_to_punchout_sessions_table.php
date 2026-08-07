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
            // Parsed off every PunchOutSetupRequest by SetupRequestParser
            // already, but previously dropped on the floor by
            // SessionManager::start() rather than persisted anywhere.
            $table->string('buyer_first_name')->nullable()->after('buyer_country');
            $table->string('buyer_last_name')->nullable()->after('buyer_first_name');
            $table->string('contact_name')->nullable()->after('buyer_last_name');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->text('supplier_setup_url')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table): void {
            $table->dropColumn(['buyer_first_name', 'buyer_last_name', 'contact_name', 'contact_email', 'supplier_setup_url']);
        });
    }
};
