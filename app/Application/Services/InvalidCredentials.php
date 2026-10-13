<?php

namespace App\Application\Services;

use RuntimeException;

/** CA-01.2: never says whether the username or the password failed. */
final class InvalidCredentials extends RuntimeException
{
}
