<?php

declare(strict_types=1);

use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\Money;

it('builds from a decimal string without float drift', function () {
    $money = Money::fromDecimal('25.99', 'aud');

    expect($money->currency())->toBe('AUD')
        ->and($money->minorUnits())->toBe(2599)
        ->and($money->toDecimalString())->toBe('25.99');
});

it('builds from minor units directly', function () {
    $money = Money::fromMinorUnits(2599, 'AUD');

    expect($money->toDecimalString())->toBe('25.99');
});

it('treats zero-decimal currencies with no cents', function () {
    $money = Money::fromDecimal('1500', 'JPY');

    expect($money->minorUnits())->toBe(1500)
        ->and($money->toDecimalString())->toBe('1500');
});

it('adds two amounts in the same currency', function () {
    $total = Money::fromDecimal('10.00', 'AUD')->add(Money::fromDecimal('5.50', 'AUD'));

    expect($total->toDecimalString())->toBe('15.50');
});

it('refuses to add different currencies', function () {
    Money::fromDecimal('10.00', 'AUD')->add(Money::fromDecimal('10.00', 'USD'));
})->throws(DomainValidationException::class);

it('multiplies by a cart quantity', function () {
    $lineTotal = Money::fromDecimal('12.50', 'AUD')->multiply(4);

    expect($lineTotal->toDecimalString())->toBe('50.00');
});

it('refuses a negative quantity', function () {
    Money::fromDecimal('12.50', 'AUD')->multiply(-1);
})->throws(DomainValidationException::class);

it('rejects a malformed amount', function () {
    Money::fromDecimal('not-a-number', 'AUD');
})->throws(DomainValidationException::class);

it('rejects an invalid currency code', function () {
    Money::fromDecimal('10.00', 'AUDX');
})->throws(DomainValidationException::class);

it('compares equality by minor units and currency', function () {
    $a = Money::fromDecimal('10.00', 'AUD');
    $b = Money::fromMinorUnits(1000, 'AUD');

    expect($a->equals($b))->toBeTrue();
});

it('formats to string as amount plus currency', function () {
    expect((string) Money::fromDecimal('25.99', 'AUD'))->toBe('25.99 AUD');
});
