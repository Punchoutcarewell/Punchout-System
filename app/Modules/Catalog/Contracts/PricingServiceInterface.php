<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\ContractPriceSnapshot;
use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Shared\Exceptions\DomainValidationException;

/**
 * What Cart and Punchout depend on for pricing, never on the Product or
 * ContractPrice Eloquent models directly. Cart's future CartService and
 * Punchout's OrderMessageBuilder both consume ContractPriceSnapshot, the
 * DTO this returns, not this module's internals.
 */
interface PricingServiceInterface
{
    /**
     * Resolve the price a punchout buyer should see for a SKU right now:
     * the active contract price if one is on file, the list price
     * otherwise.
     *
     * @throws ProductNotFoundException no active product exists for the SKU
     * @throws DomainValidationException the product is priced in a different currency, conversion is not supported
     */
    public function resolveContractPrice(string $sku, string $currency): ContractPriceSnapshot;
}
