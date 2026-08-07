<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * A value failed a domain invariant, an invalid Money amount, a malformed
 * UNSPSC code, and so on. Distinct from infrastructure failures: this is
 * always a defect in the data or the caller, never a transient condition.
 */
final class DomainValidationException extends AppException {}
