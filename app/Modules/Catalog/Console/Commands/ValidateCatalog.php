<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console\Commands;

use App\Modules\Catalog\Services\CatalogValidator;
use Illuminate\Console\Command;

final class ValidateCatalog extends Command
{
    protected $signature = 'catalog:validate';

    protected $description = 'Fail if any active product is missing a UNSPSC code, contract price, unit of measure, or description.';

    public function handle(CatalogValidator $validator): int
    {
        $failures = $validator->validate();

        if ($failures === []) {
            $this->components->info('All active products pass catalogue validation.');

            return self::SUCCESS;
        }

        $this->components->error(count($failures).' active product(s) failed validation:');

        foreach ($failures as $failure) {
            $this->line("  {$failure['sku']}: ".implode(', ', $failure['issues']));
        }

        return self::FAILURE;
    }
}
