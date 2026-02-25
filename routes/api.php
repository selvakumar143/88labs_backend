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
use App\Http\Controllers\Admin\AccountManagementController as AdminAccountMgmt;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (TOKEN BASED)
|--------------------------------------------------------------------------
*/

Route::post('/customer/register', [CustomerAuthController::class, 'register']);
Route::post('/customer/login', [CustomerAuthController::class, 'login']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ROUTES
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:customer|Customer,sanctum'])->group(function () {

        // Ad Account
        Route::post('/ad-account-request', [ClientAdController::class, 'store']);
        Route::get('/client/ad-account-requests', [ClientAdController::class, 'index']);
        Route::get('/my-ad-account-requests', [ClientAdController::class, 'myRequests']);

        // Wallet
        Route::post('/wallet-topup', [ClientWallet::class, 'store']);
        Route::get('/my-wallet-topups', [ClientWallet::class, 'myRequests']);
        Route::get('/client/wallet-summary', [ClientDashboardController::class, 'walletSummary']);

        // Top Requests
        Route::post('/top-requests', [ClientTopRequest::class, 'store']);
        Route::get('/my-top-requests', [ClientTopRequest::class, 'myRequests']);

        // Dashboard
        Route::get('/client/dashboard', [ClientDashboardController::class, 'dashboard']);
        Route::get('/client/dashboard/wallet', [ClientDashboardController::class, 'wallet']);
        Route::get('/client/dashboard/active-accounts-total', [ClientDashboardController::class, 'totalActiveAccounts']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */
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

        // Account Management
        Route::get('/admin/account-management', [AdminAccountMgmt::class, 'index']);
        Route::post('/admin/account-management', [AdminAccountMgmt::class, 'store']);

        // Users
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::put('/users/{id}', [UserManagementController::class, 'update']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);

        // Admin Dashboard
        Route::get('/admin', [AdminController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

});
