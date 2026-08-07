<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punchout_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 20);
            $table->string('to_domain');
            $table->string('to_identity');
            $table->text('shared_secret');
            $table->string('sender_domain');
            $table->string('sender_identity');
            $table->string('protocol', 20)->default('cxml');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['environment', 'to_domain', 'to_identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punchout_credentials');
    }
};
