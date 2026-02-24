<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\ClientDashboardController;
use App\Http\Controllers\Web\AdminDashboardController;

/*
|--------------------------------------------------------------------------
| Redirect Root
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| CLIENT AUTH (SESSION)
|--------------------------------------------------------------------------
*/

Route::get('/login', [ClientDashboardController::class, 'showLogin'])
    ->name('client.login');

Route::post('/login', [ClientDashboardController::class, 'login']);

Route::post('/logout', [ClientDashboardController::class, 'logout'])
    ->name('client.logout');

/*
|--------------------------------------------------------------------------
| CLIENT DASHBOARD (SESSION PROTECTED)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:customer|Customer'])
    ->prefix('client')
    ->group(function () {

        Route::get('/dashboard', [ClientDashboardController::class, 'dashboard'])
            ->name('client.dashboard');
});

/*
|--------------------------------------------------------------------------
| ADMIN AUTH (SESSION)
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', [AdminDashboardController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/admin/login', [AdminDashboardController::class, 'login']);

Route::post('/admin/logout', [AdminDashboardController::class, 'logout'])
    ->name('admin.logout');

/*
|--------------------------------------------------------------------------
| ADMIN DASHBOARD (SESSION PROTECTED)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin|Admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'dashboard'])
            ->name('admin.dashboard');
});

/*
|--------------------------------------------------------------------------
| TEST UI (FOR SANCTUM API TESTING)
|--------------------------------------------------------------------------
*/

Route::view('/test/login', 'test.login');
Route::view('/test/client-dashboard', 'test.client-dashboard');
Route::view('/test/admin-dashboard', 'test.admin-dashboard');
