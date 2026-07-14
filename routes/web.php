<?php

use App\Http\Controllers\Admin\CashflowController;
use App\Http\Controllers\Admin\ManualInvoiceController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\Admin\DailyExpenseController;
use App\Http\Controllers\Admin\CashierController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FloorPlanController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\StoreProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LandingPageController;
use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Support\Facades\Route;

// Customer order via QR (public, no auth)
Route::get('/order/{storeSlug}/{qrCode}', [CustomerOrderController::class, 'show'])->name('order.show');
Route::post('/order', [CustomerOrderController::class, 'store'])->name('order.store');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// Auth routes
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Admin routes
Route::prefix('admin')->middleware(EnsureAuthenticated::class)->group(function () {
    // Dashboard (owner, admin, cashier, staff)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin,cashier,staff')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    });

    // Cashier (owner, admin, cashier)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin,cashier')->group(function () {
        Route::get('/cashier', [CashierController::class, 'index'])->name('admin.cashier.index');
        Route::post('/cashier', [CashierController::class, 'store'])->name('admin.cashier.store');
        Route::post('/cashier/check-promo', [CashierController::class, 'checkPromo'])->name('admin.cashier.check-promo');
        Route::post('/cashier/rental/{order}/extend', [CashierController::class, 'extendRental'])->name('admin.cashier.extend-rental');
        Route::get('/cashier/pending-orders', [CashierController::class, 'pendingOrders'])->name('admin.cashier.pending-orders');

        // Shifts
        Route::get('/shifts', [ShiftController::class, 'index'])->name('admin.shifts.index');
        Route::get('/shifts/active', [ShiftController::class, 'active'])->name('admin.shifts.active');
        Route::post('/shifts/open', [ShiftController::class, 'open'])->name('admin.shifts.open');
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('admin.shifts.close');
        Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('admin.shifts.show');
    });

    // Struk Manual (owner, admin, cashier)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin,cashier')->group(function () {
        Route::get('/manual-invoice', [ManualInvoiceController::class, 'index'])->name('admin.manual-invoice.index');
        Route::post('/manual-invoice', [ManualInvoiceController::class, 'store'])->name('admin.manual-invoice.store');
        Route::get('/manual-invoice/{order}/receipt', [ManualInvoiceController::class, 'receipt'])->name('admin.manual-invoice.receipt');
    });

    // Orders / Pesanan (owner, admin, cashier)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin,cashier')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('admin.orders.index');
        Route::get('/sales', [SalesController::class, 'index'])->name('admin.sales.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('admin.orders.show');
        Route::get('/orders/{order}/detail', [OrderController::class, 'detail'])->name('admin.orders.detail');
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('admin.orders.receipt');
        Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('admin.orders.invoice');
        Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])->name('admin.orders.pay');
        Route::put('/orders/{order}', [OrderController::class, 'update'])->name('admin.orders.update');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('admin.orders.destroy');
        Route::post('/orders/{order}/feedback', [\App\Http\Controllers\Admin\FeedbackController::class, 'store'])->name('admin.orders.feedback');

        // Refunds
        Route::get('/refunds', [RefundController::class, 'index'])->name('admin.refunds.index');
        Route::post('/refunds/lookup', [RefundController::class, 'lookup'])->name('admin.refunds.lookup');
        Route::get('/refunds/create/{order}', [RefundController::class, 'create'])->name('admin.refunds.create');
        Route::post('/refunds', [RefundController::class, 'store'])->name('admin.refunds.store');
        Route::get('/refunds/{refund}', [RefundController::class, 'show'])->name('admin.refunds.show');
    });

    // Cashflow & Pembukuan Harian (owner, admin, cashier)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin,cashier')->group(function () {
        Route::get('/cashflow', [CashflowController::class, 'index'])->name('admin.cashflow.index');
        Route::get('/expense-book', [DailyExpenseController::class, 'index'])->name('admin.expense-book.index');
        Route::post('/expense-book', [DailyExpenseController::class, 'store'])->name('admin.expense-book.store');
        Route::put('/expense-book/{dailyExpense}', [DailyExpenseController::class, 'update'])->name('admin.expense-book.update');
        Route::delete('/expense-book/{dailyExpense}', [DailyExpenseController::class, 'destroy'])->name('admin.expense-book.destroy');
    });

    // Reports / Laporan (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/reports/print', [ReportController::class, 'print'])->name('admin.reports.print');
    });

    // Categories (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('admin.categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');
    });

    // Products (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('admin.products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('admin.products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('admin.products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('admin.products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');
    });

    // Inventory / Barang Masuk (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('admin.inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('admin.inventory.store');
        Route::put('/inventory/{stockIn}', [InventoryController::class, 'update'])->name('admin.inventory.update');
        Route::delete('/inventory/{stockIn}', [InventoryController::class, 'destroy'])->name('admin.inventory.destroy');
    });

    // Customers / Pelanggan (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('admin.customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('admin.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('admin.customers.destroy');
        Route::get('/customers/{customer}/history', [CustomerController::class, 'history'])->name('admin.customers.history');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
    });

    // Users / Karyawan (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });

    // Promos / Voucher (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/promos', [\App\Http\Controllers\Admin\PromoController::class, 'index'])->name('admin.promos.index');
        Route::get('/promos/create', [\App\Http\Controllers\Admin\PromoController::class, 'create'])->name('admin.promos.create');
        Route::post('/promos', [\App\Http\Controllers\Admin\PromoController::class, 'store'])->name('admin.promos.store');
        Route::get('/promos/{promo}/edit', [\App\Http\Controllers\Admin\PromoController::class, 'edit'])->name('admin.promos.edit');
        Route::put('/promos/{promo}', [\App\Http\Controllers\Admin\PromoController::class, 'update'])->name('admin.promos.update');
        Route::delete('/promos/{promo}', [\App\Http\Controllers\Admin\PromoController::class, 'destroy'])->name('admin.promos.destroy');
    });

    // Suppliers (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/suppliers', [\App\Http\Controllers\Admin\SupplierController::class, 'index'])->name('admin.suppliers.index');
        Route::post('/suppliers', [\App\Http\Controllers\Admin\SupplierController::class, 'store'])->name('admin.suppliers.store');
        Route::put('/suppliers/{supplier}', [\App\Http\Controllers\Admin\SupplierController::class, 'update'])->name('admin.suppliers.update');
        Route::delete('/suppliers/{supplier}', [\App\Http\Controllers\Admin\SupplierController::class, 'destroy'])->name('admin.suppliers.destroy');
    });

    // Purchase Orders (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/purchase-orders', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'index'])->name('admin.purchase-orders.index');
        Route::get('/purchase-orders/create', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'create'])->name('admin.purchase-orders.create');
        Route::post('/purchase-orders', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'store'])->name('admin.purchase-orders.store');
        Route::get('/purchase-orders/{purchaseOrder}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'show'])->name('admin.purchase-orders.show');
        Route::get('/purchase-orders/{purchaseOrder}/edit', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'edit'])->name('admin.purchase-orders.edit');
        Route::put('/purchase-orders/{purchaseOrder}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'update'])->name('admin.purchase-orders.update');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'receive'])->name('admin.purchase-orders.receive');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'cancel'])->name('admin.purchase-orders.cancel');
        Route::delete('/purchase-orders/{purchaseOrder}', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'destroy'])->name('admin.purchase-orders.destroy');
    });

    // Settings (owner & admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings/payment-methods', [SettingsController::class, 'storePaymentMethod'])->name('admin.settings.payment-methods.store');
        Route::put('/settings/payment-methods/{paymentMethod}', [SettingsController::class, 'updatePaymentMethod'])->name('admin.settings.payment-methods.update');
        Route::delete('/settings/payment-methods/{paymentMethod}', [SettingsController::class, 'destroyPaymentMethod'])->name('admin.settings.payment-methods.destroy');
    });

    // Reset data trial (owner only)
    Route::middleware(EnsureAuthenticated::class . ':owner')->group(function () {
        Route::post('/settings/reset-trial', [SettingsController::class, 'resetTrial'])->name('admin.settings.reset-trial');
    });

    // Stores (owner only)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')
        ->group(function () {
            // Store CRUD routes removed because there's only 1 store

            // Store Products
            Route::get('/stores/{store}/products', [StoreProductController::class, 'index'])->name('admin.stores.products');
            Route::post('/stores/{store}/products/add', [StoreProductController::class, 'addProduct'])->name('admin.stores.products.add');
            Route::post('/stores/{store}/products/copy', [StoreProductController::class, 'copyProducts'])->name('admin.stores.products.copy');
            Route::post('/stores/{store}/products/stock', [StoreProductController::class, 'updateStock'])->name('admin.stores.products.stock');
            Route::post('/stores/{store}/products/price', [StoreProductController::class, 'updatePrice'])->name('admin.stores.products.price');
            Route::put('/stores/{store}/products/{product}', [StoreProductController::class, 'updateProduct'])->name('admin.stores.products.update');
            Route::delete('/stores/{store}/products/{product}', [StoreProductController::class, 'destroyProduct'])->name('admin.stores.products.destroy');
            Route::post('/stores/{store}/products/{product}/reset-price', [StoreProductController::class, 'resetPrice'])->name('admin.stores.products.reset-price');

            // Floor Plan / Denah Meja
            Route::get('/stores/{store}/floor-plan', [FloorPlanController::class, 'index'])->name('admin.stores.floor-plan');
            Route::get('/stores/{store}/floor-plan/{floor}', [FloorPlanController::class, 'edit'])->name('admin.stores.floor-plan.edit');
            Route::get('/stores/{store}/floor-plan/{floor}/print-qr', [FloorPlanController::class, 'printQr'])->name('admin.stores.floor-plan.print-qr');
            Route::post('/stores/{store}/floor-plan', [FloorPlanController::class, 'storeFloor'])->name('admin.stores.floor-plan.store');
            Route::put('/stores/{store}/floor-plan/{floor}', [FloorPlanController::class, 'updateFloor'])->name('admin.stores.floor-plan.update');
            Route::delete('/stores/{store}/floor-plan/{floor}', [FloorPlanController::class, 'destroyFloor'])->name('admin.stores.floor-plan.destroy');
            Route::post('/stores/{store}/floor-plan/{floor}/save-layout', [FloorPlanController::class, 'saveLayout'])->name('admin.stores.floor-plan.save-layout');
            Route::post('/stores/{store}/floor-plan/{floor}/tables', [FloorPlanController::class, 'addTable'])->name('admin.stores.floor-plan.add-table');
            Route::post('/stores/{store}/floor-plan/{floor}/elements', [FloorPlanController::class, 'addElement'])->name('admin.stores.floor-plan.add-element');
            Route::delete('/stores/{store}/floor-plan/{floor}/tables/{table}', [FloorPlanController::class, 'removeTable'])->name('admin.stores.floor-plan.remove-table');
            Route::delete('/stores/{store}/floor-plan/{floor}/elements/{element}', [FloorPlanController::class, 'removeElement'])->name('admin.stores.floor-plan.remove-element');
            Route::post('/stores/{store}/floor-plan/{floor}/tables/{table}/copy', [FloorPlanController::class, 'copyTable'])->name('admin.stores.floor-plan.copy-table');
            Route::post('/stores/{store}/floor-plan/{floor}/tables/{table}/update-meta', [FloorPlanController::class, 'updateMeta'])->name('admin.stores.floor-plan.update-meta');
            Route::post('/stores/{store}/floor-plan/{floor}/elements/{element}/copy', [FloorPlanController::class, 'copyElement'])->name('admin.stores.floor-plan.copy-element');
        });

    // Floor Plan rental actions (owner, admin, cashier)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin,cashier')->group(function () {
        Route::post('/stores/{store}/floor-plan/{floor}/tables/{table}/toggle-tuya', [FloorPlanController::class, 'toggleTuya'])->name('admin.stores.floor-plan.toggle-tuya');
        Route::post('/stores/{store}/floor-plan/{floor}/tables/{table}/start-rental', [FloorPlanController::class, 'startRental'])->name('admin.stores.floor-plan.start-rental');
        Route::post('/stores/{store}/floor-plan/{floor}/tables/{table}/stop-rental', [FloorPlanController::class, 'stopRental'])->name('admin.stores.floor-plan.stop-rental');
    });

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::post('/notifications/mark-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('admin.notifications.mark-read');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('admin.notifications.mark-all-read');

    // Landing Page CMS (owner, admin)
    Route::middleware(EnsureAuthenticated::class . ':owner,admin')->group(function () {
        Route::get('/landing', [\App\Http\Controllers\Admin\LandingSettingsController::class, 'index'])->name('admin.landing.index');
        Route::put('/landing/settings', [\App\Http\Controllers\Admin\LandingSettingsController::class, 'updateSettings'])->name('admin.landing.settings.update');
        Route::put('/landing/sections/{section}', [\App\Http\Controllers\Admin\LandingSettingsController::class, 'updateSection'])->name('admin.landing.sections.update');
        Route::post('/landing/upload', [\App\Http\Controllers\Admin\LandingSettingsController::class, 'uploadSectionImage'])->name('admin.landing.upload');
    });
});

// Public landing page per brand
Route::get('/p/{brandSlug}', [LandingPageController::class, 'show'])->name('landing.show');

// Redirect root to dashboard or login
Route::get('/', fn () => redirect('/admin/dashboard'));
