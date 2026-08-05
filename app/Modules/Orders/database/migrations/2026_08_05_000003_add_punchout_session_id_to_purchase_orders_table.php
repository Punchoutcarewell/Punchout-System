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
            // Nullable: correlating a PO back to the punchout session that
            // produced it depends on Coupa echoing BuyerCookie back on the
            // OrderRequest, which is not confirmed to happen at all (see
            // OrderRequestParser's docblock). A PO with no match here is
            // expected, not an error. nullOnDelete rather than cascade: a
            // punchout_sessions row being pruned must never take purchase
            // order history with it.
            $table->foreignId('punchout_session_id')
                ->nullable()
                ->after('buyer_reference')
                ->constrained('punchout_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('punchout_session_id');
        });
    }
};
