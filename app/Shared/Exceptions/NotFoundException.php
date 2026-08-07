<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * A requested entity does not exist: an unknown SKU, an empty cart with no
 * matching line, and so on. A pure marker subclass, no behaviour of its
 * own, that exists so the global exception handler can map every "not
 * found" case across every module to a 404 JSON response in one place,
 * instead of each module wiring that up separately.
 */
abstract class NotFoundException extends AppException {}
