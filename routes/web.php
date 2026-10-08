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

    // Databases (MySQL & PostgreSQL)
    Route::resource('databases', DatabaseController::class)->only(['index', 'store', 'destroy']);
    Route::get('/databases/{database}/studio', [DatabaseController::class, 'studio'])->name('databases.studio');
    Route::post('/databases/{database}/studio/query', [DatabaseController::class, 'query'])->name('databases.studio.query');
    Route::post('/databases/{database}/studio/create-table', [DatabaseController::class, 'createTable'])->name('databases.studio.create-table');
    Route::post('/databases/{database}/studio/drop-table', [DatabaseController::class, 'dropTable'])->name('databases.studio.drop-table');
    Route::get('/databases/{database}/studio/export', [DatabaseController::class, 'export'])->name('databases.studio.export');
    Route::post('/databases/{database}/studio/import', [DatabaseController::class, 'import'])->name('databases.studio.import');
    Route::get('/databases/{database}/manage', [DatabaseController::class, 'autologin'])->name('databases.autologin');
    Route::any('/database-manager', [DatabaseController::class, 'manager'])->name('databases.manager');

    // UFW Firewall
    Route::resource('firewall', FirewallController::class)->only(['index', 'store', 'destroy']);

    // VPS One-Click Installer Guide
    Route::get('/installer', [InstallerController::class, 'index'])->name('installer.index');
});
