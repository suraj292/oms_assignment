<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Legacy user endpoint
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin-only routes
    Route::middleware('role:Admin')->group(function () {
        Route::post('/products', [App\Http\Controllers\Api\ProductController::class, 'store']);
        Route::put('/products/{product}', [App\Http\Controllers\Api\ProductController::class, 'update']);
        Route::delete('/products/{product}', [App\Http\Controllers\Api\ProductController::class, 'destroy']);

        Route::post('/customers', [App\Http\Controllers\Api\CustomerController::class, 'store']);
        Route::put('/customers/{customer}', [App\Http\Controllers\Api\CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [App\Http\Controllers\Api\CustomerController::class, 'destroy']);
        Route::delete('/orders/{order}', [App\Http\Controllers\Api\OrderController::class, 'destroy']);
    });

    // Admin and Staff routes
    Route::middleware('role:Admin,Staff')->group(function () {
        Route::get('/products', [App\Http\Controllers\Api\ProductController::class, 'index']);
        Route::get('/products/{product}', [App\Http\Controllers\Api\ProductController::class, 'show']);

        Route::get('/customers', [App\Http\Controllers\Api\CustomerController::class, 'index']);
        Route::get('/customers/{customer}', [App\Http\Controllers\Api\CustomerController::class, 'show']);

        Route::get('/orders', [App\Http\Controllers\Api\OrderController::class, 'index']);
        Route::post('/orders', [App\Http\Controllers\Api\OrderController::class, 'store']);
        Route::get('/orders/{order}', [App\Http\Controllers\Api\OrderController::class, 'show']);
        Route::put('/orders/{order}', [App\Http\Controllers\Api\OrderController::class, 'update']);
        Route::patch('/orders/{order}/status', [App\Http\Controllers\Api\OrderController::class, 'updateStatus']);

        Route::post('/orders/{order}/documents', [App\Http\Controllers\Api\OrderDocumentController::class, 'store']);
        Route::delete('/orders/{order}/documents/{document}', [App\Http\Controllers\Api\OrderDocumentController::class, 'destroy']);

        Route::post('/uploads/init', [App\Http\Controllers\Api\ChunkedUploadController::class, 'initialize']);
        Route::post('/uploads/{uploadId}/chunk', [App\Http\Controllers\Api\ChunkedUploadController::class, 'uploadChunk']);
        Route::get('/uploads/{uploadId}/status', [App\Http\Controllers\Api\ChunkedUploadController::class, 'getStatus']);
        Route::post('/uploads/{uploadId}/complete', [App\Http\Controllers\Api\ChunkedUploadController::class, 'complete']);
        Route::delete('/uploads/{uploadId}', [App\Http\Controllers\Api\ChunkedUploadController::class, 'cancel']);

        Route::delete('/order-documents/{document}', [App\Http\Controllers\Api\OrderDocumentController::class, 'destroy']);

        Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    });
});
