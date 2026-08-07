<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Previously a credential authenticated any request presenting the right
 * shared secret and To identity, regardless of who the request claimed to
 * be From: any caller holding the secret could address an outbound
 * PunchOutOrderMessage to an arbitrary buyer identity. from_domain and
 * from_identity make a credential belong to one specific buying
 * organisation, and join the unique index so a single supplier identity
 * can eventually serve more than one buyer, each with its own secret.
 *
 * Existing rows are backfilled from the one real buyer identity this
 * project has ever tested against (see tests/Fixtures/Cxml, Amazon's own
 * supplier questionnaire sample): DUNS/COUPA1. That is a real value, not
 * a placeholder, but it must be revisited once Coupa's actual production
 * From identity is confirmed.
 *
 * from_domain/from_identity are capped at 100 chars, shorter than
 * to_domain/to_identity's uncapped default: on MySQL/utf8mb4 the
 * 5-column composite unique index below is evaluated in bytes (4 per
 * char), and environment(20) + to_domain(255) + to_identity(255) +
 * from_domain(255) + from_identity(255) exceeds InnoDB's 3072-byte key
 * limit, a real value here is a short DUNS-style identity, never
 * anywhere near 100 characters. SQLite, used locally, enforces no such
 * limit either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_credentials', function (Blueprint $table): void {
            $table->dropUnique(['environment', 'to_domain', 'to_identity']);
            $table->string('from_domain', 100)->nullable()->after('environment');
            $table->string('from_identity', 100)->nullable()->after('from_domain');
        });

        DB::table('punchout_credentials')
            ->whereNull('from_domain')
            ->update(['from_domain' => 'DUNS', 'from_identity' => 'COUPA1']);

        // Left nullable at the schema level rather than a NOT NULL
        // alteration (which needs doctrine/dbal on some drivers): every
        // row is backfilled above, and the Admin form makes both fields
        // required for anything created from here on.
        Schema::table('punchout_credentials', function (Blueprint $table): void {
            $table->unique(['environment', 'to_domain', 'to_identity', 'from_domain', 'from_identity'], 'punchout_credentials_full_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_credentials', function (Blueprint $table): void {
            $table->dropUnique('punchout_credentials_full_identity_unique');
            $table->dropColumn(['from_domain', 'from_identity']);
            $table->unique(['environment', 'to_domain', 'to_identity']);
        });
    }
};
