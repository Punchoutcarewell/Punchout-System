<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Shared\ValueObjects\UnspscCode;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Backs `catalog:validate`: every active product must have a valid
 * UNSPSC code, at least one contract price, a unit of measure, and a
 * description before it belongs in a PunchOutOrderMessage. Coupa's own
 * onboarding treats a missing field here as a blocking defect, not a
 * warning, so this runs as a hard gate.
 */
final class CatalogValidator
{
    /**
     * @return array<int, array{sku: string, issues: string[]}>
     */
    public function validate(): array
    {
        $failures = [];

        Product::query()
            ->where('is_active', true)
            ->with('contractPrices')
            ->chunkById(200, function (Collection $products) use (&$failures): void {
                foreach ($products as $product) {
                    $issues = $this->issuesFor($product);

                    if ($issues !== []) {
                        $failures[] = ['sku' => $product->sku, 'issues' => $issues];
                    }
                }
            });

        return $failures;
    }

    /**
     * @return string[]
     */
    private function issuesFor(Product $product): array
    {
        $issues = [];

        try {
            UnspscCode::fromString($product->unspsc_code);
        } catch (Throwable) {
            $issues[] = 'invalid or missing UNSPSC code';
        }

        if (trim($product->unit_of_measure) === '') {
            $issues[] = 'missing unit of measure';
        }

        if (trim($product->description) === '') {
            $issues[] = 'missing description';
        }

        if ($product->contractPrices->isEmpty()) {
            $issues[] = 'no contract price on file';
        }

        return $issues;
    }
}
