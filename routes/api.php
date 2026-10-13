<?php

use App\Adapters\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Contract: docs/spec-python/api-contract.md §3. Prefix /api/v1 is set in bootstrap/app.php.
Route::get('/health', HealthController::class);
