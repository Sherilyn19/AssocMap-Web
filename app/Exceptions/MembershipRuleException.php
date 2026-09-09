<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/** Expected business-rule failure: this message is safe to explain to the user. */
final class MembershipRuleException extends DomainException
{
}
