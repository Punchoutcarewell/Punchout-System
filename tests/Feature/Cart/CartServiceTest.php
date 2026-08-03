<?php

declare(strict_types=1);

use App\Modules\Cart\Exceptions\CartItemNotFoundException;
use App\Modules\Cart\Exceptions\CartNotFoundException;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Shared\Exceptions\DomainValidationException;

it('adds a new item and creates the cart on first add', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    $summary = app(CartService::class)->addItem($sessionId, $product->sku, 2);

    expect($summary->lines)->toHaveCount(1)
        ->and($summary->lines[0]->sku)->toBe('CW-4021')
        ->and($summary->lines[0]->quantity)->toBe(2)
        ->and($summary->total->toDecimalString())->toBe('51.98')
        ->and($summary->itemCount)->toBe(2);
});

it('increments quantity when the same SKU is added twice', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '10.00', 'currency' => 'AUD']);
    $service = app(CartService::class);

    $service->addItem($sessionId, $product->sku, 2);
    $summary = $service->addItem($sessionId, $product->sku, 3);

    expect($summary->lines)->toHaveCount(1)
        ->and($summary->lines[0]->quantity)->toBe(5);
});

it('throws for an unknown SKU', function () {
    $sessionId = issueTestPunchoutSession()->id;

    app(CartService::class)->addItem($sessionId, 'DOES-NOT-EXIST', 1);
})->throws(ProductNotFoundException::class);

it('rejects a quantity below 1', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct();

    app(CartService::class)->addItem($sessionId, $product->sku, 0);
})->throws(DomainValidationException::class);

it('updates a line quantity', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '10.00', 'currency' => 'AUD']);
    $service = app(CartService::class);

    $service->addItem($sessionId, $product->sku, 1);
    $summary = $service->updateQuantity($sessionId, $product->sku, 9);

    expect($summary->lines[0]->quantity)->toBe(9)
        ->and($summary->total->toDecimalString())->toBe('90.00');
});

it('throws updating a quantity when no cart exists yet', function () {
    $sessionId = issueTestPunchoutSession()->id;

    app(CartService::class)->updateQuantity($sessionId, 'CW-4021', 2);
})->throws(CartNotFoundException::class);

it('throws updating a SKU that is not in the cart', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct(['sku' => 'CW-4021']);
    $service = app(CartService::class);

    $service->addItem($sessionId, $product->sku, 1);
    $service->updateQuantity($sessionId, 'CW-SOMETHING-ELSE', 2);
})->throws(CartItemNotFoundException::class);

it('removes a line and recalculates the total', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $a = createTestProduct(['sku' => 'CW-A', 'list_price' => '10.00', 'currency' => 'AUD']);
    $b = createTestProduct(['sku' => 'CW-B', 'list_price' => '5.00', 'currency' => 'AUD']);
    $service = app(CartService::class);

    $service->addItem($sessionId, $a->sku, 1);
    $service->addItem($sessionId, $b->sku, 1);
    $summary = $service->removeItem($sessionId, $a->sku);

    expect($summary->lines)->toHaveCount(1)
        ->and($summary->lines[0]->sku)->toBe('CW-B')
        ->and($summary->total->toDecimalString())->toBe('5.00');
});

it('returns an empty summary for a session with no cart yet, rather than throwing', function () {
    $sessionId = issueTestPunchoutSession()->id;

    $summary = app(CartService::class)->summary($sessionId);

    expect($summary->lines)->toBe([])
        ->and($summary->itemCount)->toBe(0)
        ->and($summary->total->isZero())->toBeTrue();
});
