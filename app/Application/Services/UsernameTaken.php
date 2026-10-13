<?php

namespace App\Application\Services;

use RuntimeException;

/** CA-02.2: repeated username → 409 USERNAME_TAKEN. */
final class UsernameTaken extends RuntimeException
{
}
