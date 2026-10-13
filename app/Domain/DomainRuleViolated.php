<?php

namespace App\Domain;

use DomainException;

/**
 * Base error for any broken domain invariant. Plain PHP (Article I): the HTTP adapter maps it to the contract body.
 */
class DomainRuleViolated extends DomainException
{
}
