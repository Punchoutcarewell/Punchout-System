<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signed, not unsigned: a purchase order is never blocked by low
     * stock (see InventoryService::deduct()), so this can go negative.
     * A negative value IS the "how much extra needs to be added" figure
     * Admin needs, there is no separate backorder/shortfall column to
     * keep in sync with it.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->integer('stock_quantity')->default(0)->after('pack_size');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('stock_quantity');
        });
    }
};
