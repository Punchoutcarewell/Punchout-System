<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console\Commands;

use App\Modules\Catalog\Services\CatalogImporter;
use Illuminate\Console\Command;

final class ImportCatalog extends Command
{
    protected $signature = 'catalog:import {path : Path to the catalogue CSV file}';

    protected $description = 'Import the product catalogue from a CSV file, producing a report rather than failing silently.';

    public function handle(CatalogImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $report = $importer->importFromCsvFile($path);

        $this->components->info("Created {$report->created}, updated {$report->updated}, {$report->totalProcessed()} row(s) processed.");

        if ($report->hasIssues()) {
            $this->components->warn(count($report->issues).' row(s) skipped:');

            $this->table(
                ['Row', 'Reason'],
                array_map(
                    fn ($issue): array => [$issue->rowNumber, $issue->reason],
                    $report->issues,
                ),
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
