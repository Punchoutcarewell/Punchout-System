<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\Money;

/**
 * The whole cart, protocol-neutral, as it stands at the moment of
 * transfer. See CartLineSnapshot for why this shape exists.
 */
final readonly class CartSnapshot
{
    /**
     * @param  CartLineSnapshot[]  $lines
     */
    public function __construct(
        public array $lines,
        public string $currency,
    ) {
        if ($this->lines === []) {
            throw DomainValidationException::withContext(
                'A CartSnapshot cannot be built from an empty cart.',
            );
        }
    }

    public function total(): Money
    {
        return array_reduce(
            $this->lines,
            fn (Money $carry, CartLineSnapshot $line): Money => $carry->add($line->lineTotal()),
            Money::zero($this->currency),
        );
    }
}
