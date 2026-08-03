<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punchout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->text('buyer_cookie');
            $table->text('browser_form_post_url');
            // Captured from the inbound PunchOutSetupRequest so the
            // outbound PunchOutOrderMessage can reverse them (From/To
            // swap identity roles between the two message directions).
            $table->string('from_domain')->nullable();
            $table->string('from_identity')->nullable();
            $table->string('to_domain')->nullable();
            $table->string('to_identity')->nullable();
            $table->string('buyer_user_email')->nullable();
            $table->string('buyer_unique_name')->nullable();
            $table->string('buyer_business_unit')->nullable();
            $table->string('buyer_country', 2)->nullable();
            $table->string('operation', 20)->default('create');
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punchout_sessions');
    }
};
