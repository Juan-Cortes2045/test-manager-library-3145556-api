<?php

namespace App\Application\Services;

use RuntimeException;

/** 404 NOT_FOUND: unknown or soft-deleted resource. */
final class NotFound extends RuntimeException
{
}
