<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_logs', function (Blueprint $table): void {
            // MySQL indexes a foreignId column automatically; PostgreSQL
            // does not. SessionManager and Admin both query punchout_logs
            // by session_id (findLatestOutbound(), the session detail
            // view), an unindexed FK on Postgres would make every one of
            // those a full table scan as the table grows.
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_logs', function (Blueprint $table): void {
            $table->dropIndex(['session_id']);
        });
    }
};
