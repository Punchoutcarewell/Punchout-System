<?php

declare(strict_types=1);

namespace App\Modules\Cart\Exceptions;

use App\Shared\Exceptions\AppException;

/**
 * A transfer to Coupa was attempted against a cart with no items. Not a
 * "not found" case, the cart exists, it's just empty, so this stays a
 * plain AppException rather than extending NotFoundException.
 */
final class EmptyCartException extends AppException {}
