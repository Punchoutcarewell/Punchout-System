<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            // Not a foreign key into products, see the CartItem model's docblock.
            $table->string('sku');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3);
            $table->timestamps();

            $table->unique(['cart_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
