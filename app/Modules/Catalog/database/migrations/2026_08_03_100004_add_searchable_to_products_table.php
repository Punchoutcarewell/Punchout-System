<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A generated tsvector column with a GIN index is a PostgreSQL-specific
     * feature, this is what staging and production run. Local development
     * and the test suite run on sqlite by default (see config/database.php),
     * which has no equivalent, CatalogSearchService falls back to a
     * LIKE-based query there. This migration is a deliberate no-op on any
     * driver other than pgsql rather than a portability compromise on the
     * production search itself.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE products
            ADD COLUMN searchable tsvector
            GENERATED ALWAYS AS (
                to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, '') || ' ' || coalesce(sku, ''))
            ) STORED
        SQL);

        DB::statement('CREATE INDEX products_searchable_index ON products USING GIN (searchable)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS products_searchable_index');
        DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS searchable');
    }
};
