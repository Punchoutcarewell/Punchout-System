<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unspsc_references', function (Blueprint $table): void {
            $table->string('code', 8)->primary();
            $table->string('segment', 2);
            $table->string('family', 2);
            $table->string('class', 2);
            $table->string('commodity', 2);
            $table->string('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unspsc_references');
    }
};
