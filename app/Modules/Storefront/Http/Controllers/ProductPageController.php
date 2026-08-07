<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Catalog\Contracts\CatalogSearchInterface;
use App\Modules\Catalog\Contracts\PricingServiceInterface;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ProductPageController
{
    public function __construct(
        private readonly CatalogSearchInterface $catalog,
        private readonly PricingServiceInterface $pricing,
    ) {}

    public function show(string $sku): Response
    {
        $product = $this->catalog->find($sku);

        if ($product === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $contractPrice = $this->pricing->resolveContractPrice($sku, $product->listPrice->currency());

        return Inertia::render('Product/Show', [
            'product' => $product,
            'contractPrice' => $contractPrice->contractPrice,
        ]);
    }
}
