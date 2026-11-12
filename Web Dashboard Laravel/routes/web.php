
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;

// Halaman awal
Route::redirect('/', '/dashboard');

// Halaman login
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Halaman yang memerlukan login
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

    Route::get('/dashboard/data', [DashboardController::class, 'data'])
    ->name('dashboard.data');

    Route::get('/riwayat', [HistoryController::class, 'index'])
    ->name('history');
    
    Route::get('/riwayat/export', [HistoryController::class, 'export'])
    ->name('history.export');
});

