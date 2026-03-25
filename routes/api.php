<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Kasir
|--------------------------------------------------------------------------
|
| API untuk aplikasi kasir (mobile/desktop).
| Auth: Laravel Sanctum (Bearer token).
|
*/

// Public
Route::get('/health', fn () => response()->json(['ok' => true, 'message' => 'API siap']));
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Konteks toko
    Route::get('/cashier/store', [CashierController::class, 'store']);
    Route::get('/cashier/stores', [CashierController::class, 'stores']);

    // Produk & kategori
    Route::get('/cashier/products', [CashierController::class, 'products']);
    Route::get('/cashier/categories', [CashierController::class, 'categories']);

    // Meja & metode pembayaran
    Route::get('/cashier/tables', [CashierController::class, 'tables']);
    Route::get('/cashier/payment-methods', [CashierController::class, 'paymentMethods']);

    // Transaksi kasir
    Route::get('/cashier/orders', [CashierController::class, 'orderHistory']);
    Route::post('/cashier/orders', [CashierController::class, 'storeOrder']);

    // Pesanan dari meja (QR)
    Route::get('/cashier/pending-orders', [CashierController::class, 'pendingOrders']);
    Route::get('/cashier/orders/{id}', [CashierController::class, 'showOrder']);
    Route::post('/cashier/orders/{id}/pay', [CashierController::class, 'payOrder']);

    // Denah meja & ringkasan
    Route::get('/cashier/floor-plan', [CashierController::class, 'floorPlan']);
    Route::get('/cashier/summary', [CashierController::class, 'summary']);

    // Pengeluaran harian
    Route::get('/cashier/expenses', [CashierController::class, 'expenses']);
    Route::post('/cashier/expenses', [CashierController::class, 'storeExpense']);
});
