<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->boolean('has_discrepancy')->default(false)->after('status');
            // Human-readable, one issue per line: this is surfaced directly
            // in Admin, not machine-parsed anywhere, so a plain text blob
            // is enough, no need for a structured/JSON column.
            $table->text('discrepancy_details')->nullable()->after('has_discrepancy');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['has_discrepancy', 'discrepancy_details']);
        });
    }
};
