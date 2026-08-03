<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Exceptions;

use App\Shared\Exceptions\AppException;

/**
 * The request body was not well-formed XML, or failed the safety checks in
 * XmlSecurity. Maps to a cXML Status code 400, never to an HTML error page:
 * Coupa surfaces the status text directly to the buyer.
 */
final class MalformedCxmlException extends AppException {}
