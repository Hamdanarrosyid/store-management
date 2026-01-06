<?php

use App\Http\Controllers\StoreController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;



Route::group(['prefix' => 'auth'], function () {
    Route::middleware('auth:api')->group(function () {
    Route::get('/', [AuthController::class, 'me'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile'])->name('update-profile');
});

    // Unauthenticated routes
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::middleware('auth:api')->group(function() {
    // SUPER ADMIN
    Route::middleware('role:SUPER_ADMIN')->prefix('super-admin')->group(function () {
        Route::apiResource('stores', StoreController::class);
        Route::apiResource('users', UserManagementController::class)->only([
            'index', 'store', 'show', 'update', 'destroy'
        ]);
    });
    
    // ADMIN (hanya toko sendiri)
    Route::middleware(['role:ADMIN', 'store.scope'])->prefix('admin')->group(function () {
        // CRUD kasir (toko sendiri)
        Route::apiResource('cashiers', UserManagementController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        // CRUD produk (toko sendiri)
        Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        // view sales (toko sendiri)
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);

    });

    // CASHIER (toko sendiri)
    Route::middleware(['role:ADMIN,CASHIER', 'store.scope'])->prefix('cashier')->group(function () {
        // list products, create sales, list sales (toko sendiri)
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);
    });
});



Route::get('/', function() {
    return response()->json(['message' => 'Welcome to the API'], 200);
})->name('welcome');