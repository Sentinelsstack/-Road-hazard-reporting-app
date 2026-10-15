<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HazardController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ImageController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/hazards', [HazardController::class, 'index']);
    Route::post('/hazards', [HazardController::class, 'store']);
    Route::get('/hazards/{hazard}', [HazardController::class, 'show']);
    Route::put('/hazards/{hazard}', [HazardController::class, 'update']);
    Route::delete('/hazards/{hazard}', [HazardController::class, 'destroy']);

    Route::get('/hazards/{hazard}/messages', [MessageController::class, 'index']);
    Route::post('/hazards/{hazard}/messages', [MessageController::class, 'store']);

    Route::get('/images/{filename}', [ImageController::class, 'show'])->where('filename', '.*');

    Route::post('/broadcasting/auth', function () {
        return Broadcast::auth(request());
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser']);

        Route::get('/hazards', [HazardController::class, 'index']);
        Route::put('/hazards/{hazard}', [HazardController::class, 'update']);
        Route::delete('/hazards/{hazard}', [HazardController::class, 'destroy']);
    });
});
