<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Exceptions;

use App\Shared\Exceptions\AppException;

/**
 * The shared secret did not match, or no active credential exists for the
 * requested identity/environment. Maps to a cXML Status code 401.
 */
final class InvalidCredentialsException extends AppException {}
