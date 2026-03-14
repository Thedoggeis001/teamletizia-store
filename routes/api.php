<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ImageUploadController;

// Admin controllers
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductVariantController;
use App\Http\Controllers\Admin\AdminProductKeyController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

// Test API
Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Products
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Image upload
Route::post('/upload-image', [ImageUploadController::class, 'upload']);

/*
|--------------------------------------------------------------------------
| Protected routes (auth:sanctum + throttle:api)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Cart (pending order)
    |--------------------------------------------------------------------------
    */
    Route::post('/cart', [CartController::class, 'create']);
    Route::get('/cart/{order}', [CartController::class, 'show']);
    Route::post('/cart/{order}/items', [CartController::class, 'addItem']);
    Route::delete('/cart/{order}/items/{itemId}', [CartController::class, 'removeItem']);
    Route::post('/cart/{order}/checkout', [CartController::class, 'checkout'])
        ->middleware('throttle:checkout');

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/keys', [OrderController::class, 'keys']);

    /*
    |--------------------------------------------------------------------------
    | Admin routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')
        ->prefix('admin')
        ->group(function () {

            // Products
            Route::get('/products', [AdminProductController::class, 'index']);
            Route::post('/products', [AdminProductController::class, 'store']);
            Route::get('/products/{product}', [AdminProductController::class, 'show']);
            Route::put('/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

            // Variants
            Route::post('/products/{product}/variants', [AdminProductVariantController::class, 'store']);
            Route::put('/variants/{variant}', [AdminProductVariantController::class, 'update']);
            Route::delete('/variants/{variant}', [AdminProductVariantController::class, 'destroy']);

            // Product keys
            Route::get('/products/{product}/keys', [AdminProductKeyController::class, 'index']);
            Route::post('/products/{product}/keys', [AdminProductKeyController::class, 'store']);
            Route::post('/products/{product}/keys/import', [AdminProductKeyController::class, 'import']);
            Route::delete('/keys/{key}', [AdminProductKeyController::class, 'destroy']);
        });
});