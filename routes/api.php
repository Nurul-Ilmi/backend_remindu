<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| remind.u API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api automatically by bootstrap/app.php.
|
*/

// ── Public routes ─────────────────────────────────────────────────────────

Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');


// ── Authenticated routes (Sanctum) ────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
