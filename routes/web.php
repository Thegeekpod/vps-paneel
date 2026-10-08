<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// Public Authentication & Blank VPS Script Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/install.sh', [InstallerController::class, 'rawScript'])->name('installer.raw');

// Protected Panel Routes (Requires Authentication)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    // Dashboard & Metrics
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/metrics', [DashboardController::class, 'metrics'])->name('api.metrics');

    // Websites & Applications Management
    Route::resource('sites', SiteController::class);
    Route::post('sites/{site}/toggle-ssl', [SiteController::class, 'toggleSsl'])->name('sites.toggle-ssl');
    Route::post('sites/{site}/restart', [SiteController::class, 'restart'])->name('sites.restart');
    Route::post('sites/{site}/save-env', [SiteController::class, 'saveEnv'])->name('sites.save-env');

    // MySQL Databases
    Route::resource('databases', DatabaseController::class)->only(['index', 'store', 'destroy']);

    // UFW Firewall
    Route::resource('firewall', FirewallController::class)->only(['index', 'store', 'destroy']);

    // VPS One-Click Installer Guide
    Route::get('/installer', [InstallerController::class, 'index'])->name('installer.index');
});
