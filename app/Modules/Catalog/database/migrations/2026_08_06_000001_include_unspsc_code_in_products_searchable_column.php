<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "searchable" generated column added by
     * add_searchable_to_products_table did not index unspsc_code, so a
     * buyer pasting a UNSPSC classification code (the storefront's own
     * empty-search-results copy tells them they can) found nothing. A
     * generated column's expression cannot be altered in place, it has to
     * be dropped and recreated. Deliberate no-op on any driver other than
     * pgsql, matching add_searchable_to_products_table: sqlite falls back
     * to CatalogSearchService's LIKE query, which is updated separately.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS products_searchable_index');
        DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS searchable');

        DB::statement(<<<'SQL'
            ALTER TABLE products
            ADD COLUMN searchable tsvector
            GENERATED ALWAYS AS (
                to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, '') || ' ' || coalesce(sku, '') || ' ' || coalesce(unspsc_code, ''))
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

        DB::statement(<<<'SQL'
            ALTER TABLE products
            ADD COLUMN searchable tsvector
            GENERATED ALWAYS AS (
                to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, '') || ' ' || coalesce(sku, ''))
            ) STORED
        SQL);

        DB::statement('CREATE INDEX products_searchable_index ON products USING GIN (searchable)');
    }
};
