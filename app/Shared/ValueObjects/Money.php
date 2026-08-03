<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Exceptions\DomainValidationException;
use JsonSerializable;

/**
 * An amount of money as an integer minor-unit value (cents, for a
 * 2-decimal currency) paired with an ISO 4217 currency code.
 *
 * Money is never stored or compared as a float in this codebase. A price
 * that drifts by a fraction of a cent through repeated float arithmetic is
 * exactly the kind of defect that surfaces as a mismatch during Coupa's
 * contract-price verification, where the Category Manager checks prices
 * line by line against the contract.
 *
 * Implements JsonSerializable because minorUnits and currency are private:
 * without this, a DTO carrying a Money property would serialize to an
 * empty object {} wherever it crosses a JSON boundary (an Inertia prop,
 * an API response), since PHP's default object serialization only
 * reflects public properties.
 */
final class Money implements JsonSerializable
{
    /** Currencies whose minor unit is not 2 decimal places. Extend as needed. */
    private const ZERO_DECIMAL_CURRENCIES = ['JPY', 'KRW', 'VND'];

    private function __construct(
        private readonly int $minorUnits,
        private readonly string $currency,
    ) {}

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, self::normalizeCurrency($currency));
    }

    /**
     * @param  string|float  $amount  a decimal amount, e.g. "25.99" or 25.99
     */
    public static function fromDecimal(string|float $amount, string $currency): self
    {
        $currency = self::normalizeCurrency($currency);
        $exponent = self::exponentFor($currency);

        // Parsed via string so a float's binary representation never has a
        // chance to shift the amount before it is fixed into minor units.
        $decimal = is_float($amount)
            ? number_format($amount, $exponent, '.', '')
            : trim($amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $decimal)) {
            throw DomainValidationException::withContext(
                "Invalid monetary amount [{$decimal}].",
                ['amount' => $amount, 'currency' => $currency],
            );
        }

        $factor = 10 ** $exponent;
        $minorUnits = (int) round(((float) $decimal) * $factor);

        return new self($minorUnits, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, self::normalizeCurrency($currency));
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * The decimal string representation for this currency's exponent, e.g.
     * "25.99" for AUD or "1500" for JPY. This is what gets written into a
     * cXML <Money> element.
     */
    public function toDecimalString(): string
    {
        $exponent = self::exponentFor($this->currency);
        $factor = 10 ** $exponent;

        return number_format($this->minorUnits / $factor, $exponent, '.', '');
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * Multiply by a whole quantity, the only kind of multiplication money
     * needs in this system (unit price times cart quantity).
     */
    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw DomainValidationException::withContext(
                'Cannot multiply a monetary amount by a negative quantity.',
                ['quantity' => $quantity],
            );
        }

        return new self($this->minorUnits * $quantity, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return "{$this->toDecimalString()} {$this->currency}";
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toDecimalString(),
            'currency' => $this->currency,
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw DomainValidationException::withContext(
                "Cannot combine {$this->currency} with {$other->currency}.",
                ['left_currency' => $this->currency, 'right_currency' => $other->currency],
            );
        }
    }

    private static function exponentFor(string $currency): int
    {
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }

    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw DomainValidationException::withContext(
                "Invalid ISO 4217 currency code [{$currency}].",
                ['currency' => $currency],
            );
        }

        return $currency;
    }
}
