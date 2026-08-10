<?php

use App\Http\Controllers\LaravelUpgradeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/laravel-upgrade', [LaravelUpgradeController::class, 'index'])->name('laravel-upgrade.index');
Route::post('/laravel-upgrade', [LaravelUpgradeController::class, 'upgrade'])->name('laravel-upgrade.upgrade');
Route::get('/laravel-upgrade/status/{upgrade}', [LaravelUpgradeController::class, 'status'])->name('laravel-upgrade.status');
Route::get('/laravel-upgrade/stream/{upgrade}', [LaravelUpgradeController::class, 'stream'])->name('laravel-upgrade.stream');
