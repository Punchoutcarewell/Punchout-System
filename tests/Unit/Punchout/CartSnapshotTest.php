<?php

declare(strict_types=1);

use App\Modules\Punchout\Data\CartLineSnapshot;
use App\Modules\Punchout\Data\CartSnapshot;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;

it('sums line totals into a cart total', function () {
    $cart = new CartSnapshot(
        lines: [
            new CartLineSnapshot('SKU-A', null, 2, Money::fromDecimal('10.00', 'AUD'), 'Item A', 'EA', UnspscCode::fromString('42311505'), null, null, 1),
            new CartLineSnapshot('SKU-B', null, 1, Money::fromDecimal('5.50', 'AUD'), 'Item B', 'EA', UnspscCode::fromString('42311505'), null, null, 1),
        ],
        currency: 'AUD',
    );

    expect($cart->total()->toDecimalString())->toBe('25.50');
});

it('refuses to build a snapshot from an empty cart', function () {
    new CartSnapshot(lines: [], currency: 'AUD');
})->throws(DomainValidationException::class);
