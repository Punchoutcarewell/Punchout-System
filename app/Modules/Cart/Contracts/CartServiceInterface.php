<?php

declare(strict_types=1);

namespace App\Modules\Cart\Contracts;

use App\Modules\Cart\Data\CartSummary;
use App\Modules\Cart\Exceptions\CartItemNotFoundException;
use App\Modules\Cart\Exceptions\CartNotFoundException;
use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Shared\Exceptions\DomainValidationException;

/**
 * What the Cart JSON API controller depends on. A cart is keyed by the
 * punchout session's id, an opaque integer as far as this module is
 * concerned, it never touches the Punchout module's models to get it.
 */
interface CartServiceInterface
{
    /**
     * @throws ProductNotFoundException the SKU does not exist or is not active
     * @throws DomainValidationException quantity is less than 1
     */
    public function addItem(int $sessionId, string $sku, int $quantity): CartSummary;

    /**
     * @throws CartNotFoundException
     * @throws CartItemNotFoundException
     * @throws DomainValidationException quantity is less than 1
     */
    public function updateQuantity(int $sessionId, string $sku, int $quantity): CartSummary;

    /**
     * @throws CartNotFoundException
     * @throws CartItemNotFoundException
     */
    public function removeItem(int $sessionId, string $sku): CartSummary;

    /**
     * Never throws for a session with no cart yet: returns an empty
     * summary instead, since "no cart" is the normal starting state, not
     * an error.
     */
    public function summary(int $sessionId): CartSummary;
}
