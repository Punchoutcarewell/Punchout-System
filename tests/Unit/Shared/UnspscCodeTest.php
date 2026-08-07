<?php

declare(strict_types=1);

use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\UnspscCode;

it('accepts a valid 8-digit code and splits its hierarchy', function () {
    $code = UnspscCode::fromString('42311505');

    expect($code->value())->toBe('42311505')
        ->and($code->segment())->toBe('42')
        ->and($code->family())->toBe('31')
        ->and($code->classSegment())->toBe('15')
        ->and($code->commodity())->toBe('05');
});

it('rejects a code that is too short', function () {
    UnspscCode::fromString('4231150');
})->throws(DomainValidationException::class);

it('rejects a code that is too long', function () {
    UnspscCode::fromString('423115055');
})->throws(DomainValidationException::class);

it('rejects a code with non-digit characters', function () {
    UnspscCode::fromString('4231150A');
})->throws(DomainValidationException::class);

it('compares equality by value', function () {
    expect(UnspscCode::fromString('42311505')->equals(UnspscCode::fromString('42311505')))->toBeTrue();
});

it('casts to string as the raw code', function () {
    expect((string) UnspscCode::fromString('42311505'))->toBe('42311505');
});
