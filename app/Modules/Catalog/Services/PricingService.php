<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Contracts\PricingServiceInterface;
use App\Modules\Catalog\Data\ContractPriceSnapshot;
use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Modules\Catalog\Models\Product;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;
use Illuminate\Support\Facades\Storage;

final class PricingService implements PricingServiceInterface
{
    public function resolveContractPrice(string $sku, string $currency): ContractPriceSnapshot
    {
        $product = Product::query()->where('sku', $sku)->where('is_active', true)->first();

        if ($product === null) {
            throw ProductNotFoundException::withContext(
                "No active product found for SKU [{$sku}].",
                ['sku' => $sku],
            );
        }

        return $this->snapshotFor($product, $currency);
    }

    public function resolveContractPrices(array $skus, string $currency): array
    {
        $products = Product::query()
            ->whereIn('sku', $skus)
            ->where('is_active', true)
            // Eager-loaded so Product::activeContractPrice() resolves in
            // memory per product below, not with a fresh query each time,
            // this is the entire point of this method existing.
            ->with('contractPrices')
            ->get()
            ->keyBy('sku');

        $snapshots = [];

        foreach ($skus as $sku) {
            $product = $products->get($sku);

            if ($product === null) {
                throw ProductNotFoundException::withContext(
                    "No active product found for SKU [{$sku}].",
                    ['sku' => $sku],
                );
            }

            $snapshots[$sku] = $this->snapshotFor($product, $currency);
        }

        return $snapshots;
    }

    private function snapshotFor(Product $product, string $currency): ContractPriceSnapshot
    {
        if (strtoupper($product->currency) !== strtoupper($currency)) {
            throw DomainValidationException::withContext(
                "Product [{$product->sku}] is priced in {$product->currency}, not the requested {$currency}. Currency conversion is not supported.",
                ['sku' => $product->sku, 'product_currency' => $product->currency, 'requested_currency' => $currency],
            );
        }

        $activeContract = $product->activeContractPrice();

        $contractPrice = $activeContract !== null
            ? Money::fromDecimal($activeContract->price, $activeContract->currency)
            : Money::fromDecimal($product->list_price, $product->currency);

        return new ContractPriceSnapshot(
            sku: $product->sku,
            supplierPartId: $product->supplier_part_id,
            contractPrice: $contractPrice,
            listPrice: Money::fromDecimal($product->list_price, $product->currency),
            unitOfMeasure: $product->unit_of_measure,
            unspscCode: UnspscCode::fromString($product->unspsc_code),
            leadTimeDays: $product->lead_time_days,
            description: $product->name,
            supplierPartAuxiliaryId: $product->supplier_part_auxiliary_id,
            manufacturerPartId: $product->manufacturer_part_id,
            manufacturerName: $product->manufacturer_name,
            packSize: $product->pack_size,
            imagePath: $product->image_path === null ? null : Storage::disk('public')->url($product->image_path),
        );
    }
}
