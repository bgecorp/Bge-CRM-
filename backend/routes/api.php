<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesRepController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebOrderController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

// Public: signing in, and product pictures (image tags cannot send a sign-in token).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('products/{product}/image', [ProductController::class, 'image']);

// Public landing page: what is on offer, and placing an order (cash on delivery).
Route::get('/offers', [WebOrderController::class, 'offers']);
Route::post('/web-orders', [WebOrderController::class, 'store'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Representatives and the super admin: view products, view customers, record
    // orders (they drive the stock prediction), and manage follow-ups.
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/export', [StoreController::class, 'export'])->middleware(EnsureSuperAdmin::class);
    Route::get('/stores/{store}', [StoreController::class, 'show']);
    Route::post('/stores/{store}/orders', [OrderController::class, 'store']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
    Route::get('/predictions', [PredictionController::class, 'index']);
    Route::get('/follow-ups', [FollowUpController::class, 'index']);
    Route::post('/follow-ups', [FollowUpController::class, 'store']);
    Route::post('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete']);
    Route::get('/sales-reps', [SalesRepController::class, 'index']);

    // Super admin only.
    Route::middleware(EnsureSuperAdmin::class)->group(function () {
        Route::get('/dashboard', DashboardController::class);

        Route::post('/stores', [StoreController::class, 'store']);
        Route::put('/stores/{store}', [StoreController::class, 'update']);
        Route::delete('/stores/{store}', [StoreController::class, 'destroy']);
        Route::post('/stores/{store}/sales', [SaleController::class, 'store']);
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
        Route::post('/stores/{store}/stock-counts', [StockCountController::class, 'store']);

        Route::get('/performance', [PerformanceController::class, 'index']);

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);

        Route::apiResource('sales-reps', SalesRepController::class)->except(['index', 'show']);

        Route::post('/products', [ProductController::class, 'store']);
        // Multipart updates (with a picture) come as POST, since PHP does not parse files on PUT.
        Route::post('products/{product}', [ProductController::class, 'update']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::delete('products/{product}/image', [ProductController::class, 'destroyImage']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/login-logs', [UserController::class, 'loginLogs']);

        Route::get('/web-orders', [WebOrderController::class, 'index']);
        Route::put('/web-orders/{webOrder}', [WebOrderController::class, 'update']);
        Route::delete('/web-orders/{webOrder}', [WebOrderController::class, 'destroy']);
    });
});
