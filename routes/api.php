<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Client\AdAccountRequestController as ClientAdController;
use App\Http\Controllers\Admin\AdAccountRequestController as AdminAdController;

use App\Http\Controllers\Client\WalletTopupController as ClientWallet;
use App\Http\Controllers\Admin\WalletTopupController as AdminWallet;
use App\Http\Controllers\Client\TopRequestController as ClientTopRequest;
use App\Http\Controllers\Admin\TopRequestController as AdminTopRequest;
use App\Http\Controllers\Client\ExchangeRequestController as ClientExchangeRequestController;
use App\Http\Controllers\Admin\ExchangeRequestController as AdminExchangeRequestController;
use App\Http\Controllers\Client\TransactionController as ClientTransactionController;
use App\Http\Controllers\Client\TransactionInvoiceController as ClientTransactionInvoiceController;
use App\Http\Controllers\Admin\AccountManagementController as AdminAccountMgmt;
use App\Http\Controllers\Admin\BusinessManagerController as AdminBusinessManager;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\TransactionInvoiceController as AdminTransactionInvoiceController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ForexRateController;
use App\Http\Controllers\ServiceController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (TOKEN BASED)
|--------------------------------------------------------------------------
*/

Route::post('/customer/register', [CustomerAuthController::class, 'register']);
Route::post('/customer/login', [CustomerAuthController::class, 'login']);
Route::post('/customer/password', [CustomerAuthController::class, 'passwordHandler']); // ✅ Added

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/password', [AdminAuthController::class, 'passwordHandler']);
/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Common (Client + Admin)
    Route::get('/forex-rates', [ForexRateController::class, 'latest']);

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ROUTES
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:customer|Customer,sanctum'])->group(function () {

    Route::put('/customer/update-profile', [CustomerAuthController::class, 'updateProfile']);

        // Ad Account
        Route::post('/client/ad-account-request', [ClientAdController::class, 'store']);
        Route::get('/client/ad-account-requests', [ClientAdController::class, 'index']);
        Route::get('/my-ad-account-requests', [ClientAdController::class, 'myRequests']);

        // Wallet
        Route::post('/wallet-topup', [ClientWallet::class, 'store']);
        Route::get('/my-wallet-topups', [ClientWallet::class, 'myRequests']);
        Route::get('/client/wallet-summary', [ClientDashboardController::class, 'walletSummary']);

        // Top Requests
        Route::post('/top-requests', [ClientTopRequest::class, 'store']);
        Route::get('/my-top-requests', [ClientTopRequest::class, 'myRequests']);

        // Transactions
        Route::get('/client/transactions', [ClientTransactionController::class, 'index']);
        Route::get('/client/transactions/export', [ClientTransactionController::class, 'export']);
        Route::get('/client/transactions/{type}/{id}/invoice', [ClientTransactionInvoiceController::class, 'show']);
        Route::get('/client/transactions/{type}/{id}/invoice/download', [ClientTransactionInvoiceController::class, 'download']);

        // Exchange Requests
        Route::post('/client/exchange-requests', [ClientExchangeRequestController::class, 'store']);
        Route::get('/client/exchange-requests', [ClientExchangeRequestController::class, 'index']);
        Route::get('/client/exchange-requests/{id}', [ClientExchangeRequestController::class, 'show']);
        Route::put('/client/exchange-requests/{id}', [ClientExchangeRequestController::class, 'update']);
        Route::delete('/client/exchange-requests/{id}', [ClientExchangeRequestController::class, 'destroy']);
        Route::get('/my-exchange-requests', [ClientExchangeRequestController::class, 'myRequests']);

        // Dashboard
        Route::get('/client/dashboard', [ClientDashboardController::class, 'dashboard']);
        Route::get('/client/dashboard/wallet', [ClientDashboardController::class, 'wallet']);
        Route::get('/client/dashboard/active-accounts-total', [ClientDashboardController::class, 'totalActiveAccounts']);

        // Notifications
        Route::get('/client/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/client/notifications/unread', [NotificationController::class, 'unread']);
        Route::get('/client/notifications/all', [NotificationController::class, 'all']);
        Route::put('/client/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/client/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Services
        Route::get('/services/get', [ServiceController::class, 'getServices']);
        Route::post('/services/update', [ServiceController::class, 'updateService']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */
    
    // PUBLIC
    Route::post('/client/set-password', [ClientAuthController::class, 'setPassword']);


    Route::middleware(['role:admin|Admin,sanctum'])->group(function () {

        // Users
        Route::post('/admin/users', [UserController::class, 'store']);

        // Clients
        Route::get('/admin/clients', [ClientController::class, 'index']);
        Route::get('/admin/clients/{client}', [ClientController::class, 'show']);
        Route::post('/admin/clients', [ClientController::class, 'store']);
        Route::put('/admin/clients/{client}', [ClientController::class, 'update']);
        Route::delete('/admin/clients/{client}', [ClientController::class, 'destroy']);

        // Ad Account
        Route::get('/admin/ad-account-requests', [AdminAdController::class, 'index']);
        Route::put('/admin/ad-account-requests/{id}', [AdminAdController::class, 'updateStatus']);

        // Wallet
        Route::get('/admin/wallet-topups', [AdminWallet::class, 'index']);
        Route::put('/admin/wallet-topups/{id}', [AdminWallet::class, 'updateStatus']);

        // Top Requests
        Route::get('/admin/top-requests', [AdminTopRequest::class, 'index']);
        Route::put('/admin/top-requests/{id}', [AdminTopRequest::class, 'update']);
        Route::delete('/admin/top-requests/{id}', [AdminTopRequest::class, 'destroy']);

        // Exchange Requests
        Route::get('/admin/exchange-requests', [AdminExchangeRequestController::class, 'index']);
        Route::get('/admin/exchange-requests/{id}', [AdminExchangeRequestController::class, 'show']);
        Route::post('/admin/exchange-requests', [AdminExchangeRequestController::class, 'store']);
        Route::put('/admin/exchange-requests/{id}', [AdminExchangeRequestController::class, 'update']);
        Route::put('/admin/exchange-requests/{id}/status', [AdminExchangeRequestController::class, 'updateStatus']);
        Route::delete('/admin/exchange-requests/{id}', [AdminExchangeRequestController::class, 'destroy']);

        // Transactions
        Route::get('/admin/transactions', [AdminTransactionController::class, 'index']);
        Route::get('/admin/transactions/export', [AdminTransactionController::class, 'export']);
        Route::get('/admin/transactions/{type}/{id}/invoice', [AdminTransactionInvoiceController::class, 'show']);
        Route::get('/admin/transactions/{type}/{id}/invoice/download', [AdminTransactionInvoiceController::class, 'download']);

        // Account Management
        Route::get('/admin/account-management', [AdminAccountMgmt::class, 'index']);
        Route::post('/admin/account-management', [AdminAccountMgmt::class, 'store']);
        Route::put('/admin/account-management/{id}', [AdminAccountMgmt::class, 'update']);

        // Business Managers
        Route::get('/admin/business-managers', [AdminBusinessManager::class, 'index']);
        Route::get('/admin/business-managers/{businessManager}', [AdminBusinessManager::class, 'show']);
        Route::post('/admin/business-managers', [AdminBusinessManager::class, 'store']);
        Route::put('/admin/business-managers/{businessManager}', [AdminBusinessManager::class, 'update']);
        Route::delete('/admin/business-managers/{businessManager}', [AdminBusinessManager::class, 'destroy']);

        // Users
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::put('/users/{id}', [UserManagementController::class, 'update']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);

        // Admin Dashboard
        Route::get('/admin', [AdminController::class, 'index']);

        // Notifications
        Route::get('/admin/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/admin/notifications/unread', [NotificationController::class, 'unread']);
        Route::get('/admin/notifications/all', [NotificationController::class, 'all']);
        Route::put('/admin/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/admin/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Legacy export alias (same implementation as transactions export)
        Route::get('/admin/export-topup', [AdminTransactionController::class, 'export']);
    });

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

});
