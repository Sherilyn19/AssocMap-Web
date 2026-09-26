<?php

namespace App\Exceptions;

use RuntimeException;

/** Signals that an authentication audit write could not be confirmed. */
final class AuthenticationAuditException extends RuntimeException {}
