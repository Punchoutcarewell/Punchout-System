<?php

declare(strict_types=1);

namespace App\Shared\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * One-off: switching DB_CONNECTION from sqlite to mysql and running the
 * (driver-agnostic) migrations gives every table its schema back, but an
 * empty database, the rows themselves never move on their own. This
 * copies every real data table, verbatim and in foreign-key-dependency
 * order, from the old sqlite file into the now-configured mysql
 * connection. Deliberately skips Laravel's own queue/cache/session
 * infrastructure tables (jobs, cache, sessions, password_reset_tokens):
 * that is transient runtime state tied to the old process, not business
 * data, and none of it is meaningful to carry over.
 *
 * Safe to run more than once: any table that already has rows on the
 * destination is left alone rather than duplicated.
 */
final class MigrateSqliteDataToMysql extends Command
{
    protected $signature = 'data:migrate-sqlite-to-mysql
        {--sqlite-path= : Absolute path to the source .sqlite file, defaults to database/database.sqlite}';

    protected $description = 'Copy every row from the local SQLite database into the configured MySQL connection.';

    /**
     * Every real data table, parents before the children that
     * foreign-key onto them. FOREIGN_KEY_CHECKS is still disabled for
     * the duration below, this ordering is belt-and-braces (it also
     * reads as documentation of the dependency graph), not load-bearing
     * on its own.
     *
     * @var string[]
     */
    private const TABLES = [
        'users',
        'categories',
        'products',
        'contract_prices',
        'unspsc_references',
        'punchout_credentials',
        'punchout_sessions',
        'punchout_logs',
        'carts',
        'cart_items',
        'purchase_orders',
        'purchase_order_lines',
        'site_settings',
    ];

    public function handle(): int
    {
        $mysql = DB::connection();

        if ($mysql->getDriverName() !== 'mysql') {
            $this->components->error('The default connection (DB_CONNECTION) is not mysql, refusing to run.');

            return self::FAILURE;
        }

        $sqlitePath = $this->option('sqlite-path') ?? database_path('database.sqlite');

        if (! is_file($sqlitePath)) {
            $this->components->error("No sqlite file at {$sqlitePath}.");

            return self::FAILURE;
        }

        // The sqlite connection's own config reads DB_DATABASE too, the
        // same env var mysql's connection now uses for its schema name:
        // left alone, DB::connection('sqlite') would try to open a file
        // literally named after the mysql database. Overridden here at
        // runtime only, .env itself is untouched.
        Config::set('database.connections.sqlite.database', $sqlitePath);
        DB::purge('sqlite');
        $sqlite = DB::connection('sqlite');

        $mysql->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TABLES as $table) {
                $this->transferTable($sqlite, $mysql, $table);
            }
        } finally {
            $mysql->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->components->info('Done.');

        return self::SUCCESS;
    }

    private function transferTable(Connection $sqlite, Connection $mysql, string $table): void
    {
        $existing = $mysql->table($table)->count();

        if ($existing > 0) {
            $this->components->warn("{$table}: already has {$existing} row(s) on mysql, skipped.");

            return;
        }

        /** @var Collection<int, object> $rows */
        $rows = $sqlite->table($table)->get();

        if ($rows->isEmpty()) {
            $this->components->info("{$table}: nothing to copy.");

            return;
        }

        foreach ($rows->chunk(500) as $chunk) {
            $mysql->table($table)->insert($chunk->map(fn (object $row): array => (array) $row)->all());
        }

        $this->components->info("{$table}: copied {$rows->count()} row(s).");
    }
}
