<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Exceptions\DomainValidationException;

/**
 * An 8-digit UNSPSC classification code: segment, family, class, and
 * commodity, 2 digits each. Coupa's PunchOutOrderMessage requires this on
 * every ItemDetail as <Classification domain="UNSPSC">, and Amazon's
 * onboarding process treats a missing or malformed code as a blocking
 * defect, not a warning.
 */
final class UnspscCode
{
    private function __construct(private readonly string $code) {}

    public static function fromString(string $code): self
    {
        $code = trim($code);

        if (! preg_match('/^\d{8}$/', $code)) {
            throw DomainValidationException::withContext(
                "UNSPSC code must be exactly 8 digits, got [{$code}].",
                ['code' => $code],
            );
        }

        return new self($code);
    }

    public function value(): string
    {
        return $this->code;
    }

    public function segment(): string
    {
        return substr($this->code, 0, 2);
    }

    public function family(): string
    {
        return substr($this->code, 2, 2);
    }

    public function classSegment(): string
    {
        return substr($this->code, 4, 2);
    }

    public function commodity(): string
    {
        return substr($this->code, 6, 2);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
