<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Catalog\Contracts\PricingServiceInterface;
use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\Money;

/**
 * Checks a received purchase order against the catalogue: does the sum of
 * its lines match its stated total, and does each line's unit price match
 * what PricingService would quote for that SKU right now. Neither check
 * blocks receiving the PO, Coupa has already committed to it by the time
 * this runs, this only ever flags the order for a human to look at in
 * Admin.
 *
 * A line price mismatch does not necessarily mean either side is wrong:
 * the catalogue price may have changed between when the buyer's cart was
 * priced and when the requisition was approved and dispatched back as a
 * PO. Flagging it is still correct, that gap is exactly what someone
 * reviewing purchase orders needs visibility into.
 */
final class PurchaseOrderReconciler
{
    public function __construct(private readonly PricingServiceInterface $pricing) {}

    public function reconcile(PurchaseOrder $purchaseOrder): void
    {
        $issues = [];
        $runningTotal = Money::zero($purchaseOrder->currency);

        foreach ($purchaseOrder->lines as $line) {
            $lineUnitPrice = Money::fromDecimal($line->unit_price, $purchaseOrder->currency);
            $runningTotal = $runningTotal->add($lineUnitPrice->multiply($line->quantity));

            $catalogueIssue = $this->compareToCatalogue($line->supplier_part_id, $line->line_number, $lineUnitPrice, $purchaseOrder->currency);

            if ($catalogueIssue !== null) {
                $issues[] = $catalogueIssue;
            }
        }

        $statedTotal = Money::fromDecimal($purchaseOrder->total, $purchaseOrder->currency);

        if (! $runningTotal->equals($statedTotal)) {
            $issues[] = "PO total ({$statedTotal}) does not equal the sum of its lines ({$runningTotal}).";
        }

        $purchaseOrder->update([
            'has_discrepancy' => $issues !== [],
            'discrepancy_details' => $issues === [] ? null : implode("\n", $issues),
        ]);
    }

    private function compareToCatalogue(string $sku, int $lineNumber, Money $lineUnitPrice, string $currency): ?string
    {
        try {
            $catalogue = $this->pricing->resolveContractPrice($sku, $currency);
        } catch (ProductNotFoundException) {
            return "Line {$lineNumber} ({$sku}): no active catalogue product found for this SKU.";
        } catch (DomainValidationException $exception) {
            return "Line {$lineNumber} ({$sku}): {$exception->getMessage()}";
        }

        if (! $lineUnitPrice->equals($catalogue->contractPrice)) {
            return "Line {$lineNumber} ({$sku}): PO unit price ({$lineUnitPrice}) does not match the current catalogue price ({$catalogue->contractPrice}).";
        }

        return null;
    }
}
