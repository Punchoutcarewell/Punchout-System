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

    /**
     * The same resolution as resolveContractPrice(), for many SKUs in a
     * fixed number of queries rather than one call (and one Product +
     * ContractPrice query pair) per SKU. Cart\Services\CartSnapshotFactory
     * is the reason this exists: a cart with N distinct lines was N+1
     * queries deep before this, exactly the shape that matters at the one
     * moment this app must not be slow, transferring the cart back to
     * Coupa.
     *
     * @param  string[]  $skus
     * @return array<string, ContractPriceSnapshot> keyed by sku
     *
     * @throws ProductNotFoundException no active product exists for one of the SKUs
     * @throws DomainValidationException one of the products is priced in a different currency, conversion is not supported
     */
    public function resolveContractPrices(array $skus, string $currency): array;
}
