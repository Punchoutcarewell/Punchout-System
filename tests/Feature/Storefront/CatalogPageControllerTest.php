<?php

declare(strict_types=1);

it('redirects a direct visit with no punchout session to the no-token page', function () {
    $this->get('/storefront')->assertRedirect(route('storefront.no-token'));
});

it('renders the catalogue index with products and categories', function () {
    $session = issueTestPunchoutSession();
    $category = createTestCategory('Wound Care');
    createTestProduct(['sku' => 'CW-4021', 'category_id' => $category->id]);

    $this->get("/storefront?token={$session->token}")
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Index')
            ->has('products.data', 1)
            ->has('categories', 1)
            ->where('query', null)
            ->where('categoryId', null)
        );
});

it('filters the catalogue by a search query', function () {
    $session = issueTestPunchoutSession();
    createTestProduct(['sku' => 'CW-4021', 'name' => 'Foam Wound Dressing', 'description' => 'Foam Wound Dressing 10cm']);
    createTestProduct(['sku' => 'CW-5010', 'name' => 'Standard Wheelchair', 'description' => 'Standard folding wheelchair']);

    $this->get("/storefront?token={$session->token}&q=Wheelchair")
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Index')
            ->has('products.data', 1)
            ->where('query', 'Wheelchair')
        );
});
