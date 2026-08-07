<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

/**
 * What OrderResponseBuilder needs to acknowledge a received OrderRequest.
 */
final readonly class OrderResponseData
{
    public function __construct(
        public int $statusCode,
        public string $statusText,
    ) {}

    public static function accepted(): self
    {
        return new self(200, 'OK');
    }
}
