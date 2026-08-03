<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('po_number')->unique();
            $table->dateTime('order_date');
            $table->decimal('total', 12, 2);
            $table->string('currency', 3);
            $table->string('buyer_reference')->nullable();
            // Orders keeps its own copy of the raw payload for its own
            // audit views, independent of Punchout's punchout_logs table:
            // this module never queries Punchout's tables directly.
            $table->longText('raw_payload');
            $table->string('status', 20)->default('received');
            $table->dateTime('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
