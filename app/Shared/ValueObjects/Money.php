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
    /** ISO 4217 currencies with no minor unit at all. */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW',
        'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /** ISO 4217 currencies whose minor unit is thousandths, not hundredths. */
    private const THREE_DECIMAL_CURRENCIES = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

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

        // A float amount is rounded to this currency's exponent here, its
        // binary representation is already inexact by the time it arrives
        // as a PHP float, so this is the one place rounding is unavoidable.
        // A string amount is never rounded: every digit is carried through
        // to minor units using string/integer arithmetic only, so a float
        // cast never gets a chance to shift the value.
        $decimal = is_float($amount)
            ? number_format($amount, $exponent, '.', '')
            : trim($amount);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $decimal, $matches)) {
            throw DomainValidationException::withContext(
                "Invalid monetary amount [{$decimal}].",
                ['amount' => $amount, 'currency' => $currency],
            );
        }

        [, $sign, $integerPart, $fractionalPart] = $matches + ['', '', '', ''];

        if (strlen($fractionalPart) > $exponent) {
            throw DomainValidationException::withContext(
                "Amount [{$decimal}] has more decimal places than {$currency} supports ({$exponent}).",
                ['amount' => $amount, 'currency' => $currency],
            );
        }

        $digits = ltrim($integerPart.str_pad($fractionalPart, $exponent, '0'), '0');
        $minorUnits = (int) ($sign.($digits === '' ? '0' : $digits));

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
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return 0;
        }

        if (in_array($currency, self::THREE_DECIMAL_CURRENCIES, true)) {
            return 3;
        }

        return 2;
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
