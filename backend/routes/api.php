<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/search', [UserController::class, 'search']);
        Route::get('/me', [UserController::class, 'show']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/me', [UserController::class, 'update']);
        Route::delete('/me', [UserController::class, 'destroy']);
        Route::post('/online-status', [UserController::class, 'updateOnlineStatus']);
    });

    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::get('/search', [ContactController::class, 'search']);
    });

    Route::prefix('chats')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/direct', [ChatController::class, 'getOrCreateDirectChat']);
        Route::get('/{id}', [ChatController::class, 'show']);
        Route::delete('/{id}', [ChatController::class, 'destroy']);
        Route::post('/{id}/read', [ChatController::class, 'markAsRead']);
        
        Route::prefix('/{chatId}/messages')->group(function () {
            Route::get('/', [MessageController::class, 'index']);
            Route::post('/', [MessageController::class, 'store']);
            Route::put('/{messageId}', [MessageController::class, 'update']);
            Route::delete('/{messageId}', [MessageController::class, 'destroy']);
            Route::post('/{messageId}/read', [MessageController::class, 'markAsRead']);
        });
    });

    Route::get('/messages/search', [MessageController::class, 'search']);
});