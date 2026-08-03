<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('supplier_part_id');
            $table->string('supplier_part_auxiliary_id')->nullable();
            $table->string('manufacturer_part_id')->nullable();
            $table->string('manufacturer_name')->nullable();
            $table->string('name');
            $table->text('description');
            $table->longText('long_description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            // Not a foreign key into unspsc_references, see that model's docblock.
            $table->string('unspsc_code', 8);
            $table->string('unit_of_measure', 10);
            $table->unsignedInteger('pack_size')->default(1);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->decimal('list_price', 12, 2);
            $table->string('currency', 3);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('unspsc_code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
