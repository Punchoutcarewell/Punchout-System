<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single row (id 1, see SiteSetting::current()), not a keyed
        // config table: there is exactly one storefront and one Admin
        // panel to brand, no need for a settings key/value scheme built
        // for a variety this app does not have.
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
