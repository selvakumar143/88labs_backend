<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Client\AdAccountRequestController as ClientAdController;
use App\Http\Controllers\Admin\AdAccountRequestController as AdminAdController;

use App\Http\Controllers\Client\WalletTopupController as ClientWallet;
use App\Http\Controllers\Admin\WalletTopupController as AdminWallet;

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
    Route::middleware(['role:customer|Customer'])->group(function () {

        // Ad Account
        Route::post('/ad-account-request', [ClientAdController::class, 'store']);
        Route::get('/my-ad-account-requests', [ClientAdController::class, 'myRequests']);

        // Wallet
        Route::post('/wallet-topup', [ClientWallet::class, 'store']);
        Route::get('/my-wallet-topups', [ClientWallet::class, 'myRequests']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin|Admin'])->group(function () {

        // Users
        Route::post('/admin/users', [UserController::class, 'store']);

        // Ad Account
        Route::get('/admin/ad-account-requests', [AdminAdController::class, 'index']);
        Route::put('/admin/ad-account-requests/{id}/approve', [AdminAdController::class, 'approve']);
        Route::put('/admin/ad-account-requests/{id}/reject', [AdminAdController::class, 'reject']);

        // Wallet
        Route::get('/admin/wallet-topups', [AdminWallet::class, 'index']);
        Route::put('/admin/wallet-topups/{id}/approve', [AdminWallet::class, 'approve']);
        Route::put('/admin/wallet-topups/{id}/reject', [AdminWallet::class, 'reject']);
    });

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

});
