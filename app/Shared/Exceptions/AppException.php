<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Exception;

/**
 * Base for every domain exception across every module. Carries structured
 * context so the global exception handler and the logs can report on a
 * failure without re-deriving what went wrong from the message string.
 */
abstract class AppException extends Exception
{
    /** @var array<string, mixed> */
    private array $context;

    /**
     * Final so that new static() in withContext() is guaranteed to resolve
     * to this exact signature no matter which subclass is instantiated.
     *
     * @param  array<string, mixed>  $context
     */
    final public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);

        $this->context = $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function withContext(string $message, array $context = []): static
    {
        return new static($message, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
