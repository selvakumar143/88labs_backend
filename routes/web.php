<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;

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

Route::get('/set-password/{token}', function (string $token) {
    return view('auth.set-password', [
        'token' => $token,
        'email' => request()->query('email', ''),
        'formTitle' => 'Set Your Password',
        'formSubtitle' => 'Enter a new password and confirm it to activate your account.',
        'submitEndpoint' => '/api/client/set-password',
        'redirectUrl' => 'https://88labs.netlify.app/login',
    ]);
})->name('password.setup');

Route::get('/reset-password/{token}', function (string $token) {
    return view('auth.set-password', [
        'token' => $token,
        'email' => request()->query('email', ''),
        'formTitle' => 'Reset Your Password',
        'formSubtitle' => 'Enter a new password and confirm it to continue.',
        'submitEndpoint' => '/api/password/reset',
        'redirectUrl' => 'https://88labs.netlify.app/login',
    ]);
})->name('password.reset');

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
