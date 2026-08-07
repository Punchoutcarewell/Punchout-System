<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punchout_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('punchout_sessions')->nullOnDelete();
            $table->string('direction', 10);
            $table->string('message_type', 30);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('raw_payload');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['message_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punchout_logs');
    }
};
