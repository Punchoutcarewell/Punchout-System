<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

/**
 * What SetupResponseBuilder needs to render a PunchOutSetupResponse.
 * A failure response has no startUrl: it carries only the status.
 */
final readonly class SetupResponseData
{
    public function __construct(
        public int $statusCode,
        public string $statusText,
        public ?string $startUrl = null,
    ) {}

    public static function success(string $startUrl): self
    {
        return new self(200, 'OK', $startUrl);
    }

    public static function failure(int $statusCode, string $statusText): self
    {
        return new self($statusCode, $statusText);
    }
}
