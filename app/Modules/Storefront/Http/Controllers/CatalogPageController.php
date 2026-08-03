<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Catalog\Contracts\CatalogSearchInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalogue browse and search page. Deliberately thin: everything it
 * does is ask Catalog for a page of results and hand the DTOs straight to
 * the Vue component as props.
 */
final class CatalogPageController
{
    public function __construct(private readonly CatalogSearchInterface $catalog) {}

    public function index(Request $request): Response
    {
        $query = $request->string('q')->toString();
        $categoryId = $request->integer('category') ?: null;

        $products = $this->catalog->search(
            query: $query !== '' ? $query : null,
            categoryId: $categoryId,
            perPage: 24,
        );

        return Inertia::render('Catalog/Index', [
            'products' => $products,
            'categories' => $this->catalog->categories(),
            'query' => $query !== '' ? $query : null,
            'categoryId' => $categoryId,
        ]);
    }
}
