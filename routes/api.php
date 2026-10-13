<?php

use App\Adapters\Http\Controllers\AuthController;
use App\Adapters\Http\Controllers\BookController;
use App\Adapters\Http\Controllers\HealthController;
use App\Adapters\Http\Controllers\LoanController;
use App\Adapters\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

// Contract: docs/spec-python/api-contract.md §3. Prefix /api/v1 is set in bootstrap/app.php.
Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt:admin')->group(function () {
    Route::post('/users', [AuthController::class, 'createUser']);
    Route::post('/books', [BookController::class, 'store']);
    Route::put('/books/{id}', [BookController::class, 'update'])->whereUuid('id');
    Route::delete('/books/{id}', [BookController::class, 'destroy'])->whereUuid('id');
    Route::put('/books/{id}/cover', [BookController::class, 'putCover'])->whereUuid('id');
    Route::delete('/books/{id}/cover', [BookController::class, 'deleteCover'])->whereUuid('id');
    Route::get('/reports/most-borrowed', [LoanController::class, 'report']);
});

Route::middleware('jwt')->group(function () {
    Route::get('/categories', [BookController::class, 'categories']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show'])->whereUuid('id');
    Route::get('/books/{id}/cover', [BookController::class, 'getCover'])->whereUuid('id');
});

Route::middleware('jwt:admin,librarian')->group(function () {
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{id}', [MemberController::class, 'show'])->whereUuid('id');
    Route::post('/members', [MemberController::class, 'store']);
    Route::get('/loans/overdue', [LoanController::class, 'overdue']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
    Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem'])->whereUuid(['loanId', 'itemId']);
});
