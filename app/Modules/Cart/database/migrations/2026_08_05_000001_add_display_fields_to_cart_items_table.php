<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            // Same display-cache reasoning as description/unit_price/
            // currency (see the CartItem model's docblock): captured when
            // the line was added, not re-resolved from Catalog on every
            // cart view. pack_size null means this line is not sold in
            // packs, quantity is a plain unit count.
            $table->string('image_path')->nullable()->after('description');
            $table->unsignedInteger('pack_size')->nullable()->after('unit_price');
            $table->string('unit_of_measure', 10)->nullable()->after('pack_size');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'pack_size', 'unit_of_measure']);
        });
    }
};
