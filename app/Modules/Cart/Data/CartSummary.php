<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Shared\ValueObjects\Money;

/**
 * What the Cart JSON API returns after every mutation, and what GET
 * /storefront/cart returns directly: exactly what the sticky cart bar and
 * cart review page need, nothing about cXML or Punchout.
 */
final readonly class CartSummary
{
    /**
     * @param  CartLineSummary[]  $lines
     */
    public function __construct(
        public array $lines,
        public Money $total,
        public int $itemCount,
    ) {}

    /**
     * @return array{lines: array<int, array<string, mixed>>, total: Money, itemCount: int}
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (CartLineSummary $line): array => $line->toArray(), $this->lines),
            'total' => $this->total,
            'itemCount' => $this->itemCount,
        ];
    }
}
