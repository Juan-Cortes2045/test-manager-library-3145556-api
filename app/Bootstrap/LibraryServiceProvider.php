<?php

namespace App\Bootstrap;

use Illuminate\Support\ServiceProvider;

/**
 * Single composition root (Article III): the only place where a port is bound to its adapter.
 * Bindings are added by T-07 onwards; no infrastructure is instantiated anywhere else.
 */
final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
