<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// Main Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/metrics', [DashboardController::class, 'metrics'])->name('api.metrics');

// Sites Management (Next.js, Laravel, WordPress, PHP)
Route::resource('sites', SiteController::class);
Route::post('sites/{site}/toggle-ssl', [SiteController::class, 'toggleSsl'])->name('sites.toggle-ssl');
Route::post('sites/{site}/restart', [SiteController::class, 'restart'])->name('sites.restart');
Route::post('sites/{site}/save-env', [SiteController::class, 'saveEnv'])->name('sites.save-env');

// Databases Management (MySQL)
Route::resource('databases', DatabaseController::class)->only(['index', 'store', 'destroy']);

// Firewall Management (UFW)
Route::resource('firewall', FirewallController::class)->only(['index', 'store', 'destroy']);

// VPS One-Click Installer Script
Route::get('installer', [InstallerController::class, 'index'])->name('installer.index');
Route::get('install.sh', [InstallerController::class, 'rawScript'])->name('installer.raw');
