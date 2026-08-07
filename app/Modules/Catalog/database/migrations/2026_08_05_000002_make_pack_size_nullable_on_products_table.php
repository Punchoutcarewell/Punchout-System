<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Not every product is sold in packs, e.g. a wheelchair sold
            // one at a time. Null means "no pack, quantity is a plain
            // count of unit_of_measure units"; a real pack_size means the
            // storefront's quantity selector counts packs, not pieces,
            // and list_price/contract price is the price of one pack.
            $table->unsignedInteger('pack_size')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('pack_size')->default(1)->change();
        });
    }
};
