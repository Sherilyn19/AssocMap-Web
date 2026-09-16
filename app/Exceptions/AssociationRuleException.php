<?php

declare(strict_types=1);

namespace App\Exceptions;

// Only this exception carries a business explanation safe to show to the administrator.
// PDOException also extends RuntimeException, so catching RuntimeException is too broad.
final class AssociationRuleException extends \RuntimeException {}
