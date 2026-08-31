<?php

use App\Http\Controllers\LaravelUpgradeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(
        'laravel-upgrade.index'
    );
});

/*
|--------------------------------------------------------------------------
| Laravel Upgrade Dashboard
|--------------------------------------------------------------------------
*/

Route::get(
    '/laravel-upgrade',
    [
        LaravelUpgradeController::class,
        'index'
    ]
)->name(
    'laravel-upgrade.index'
);

/*
|--------------------------------------------------------------------------
| Compatibility
|--------------------------------------------------------------------------
*/

Route::post(
    '/laravel-upgrade/compatibility',
    [
        LaravelUpgradeController::class,
        'compatibility'
    ]
)->name(
    'laravel-upgrade.compatibility'
);

/*
|--------------------------------------------------------------------------
| Composer Dry Run
|--------------------------------------------------------------------------
*/

Route::post(
    '/laravel-upgrade/dry-run',
    [
        LaravelUpgradeController::class,
        'dryRun'
    ]
)->name(
    'laravel-upgrade.dry-run'
);

/*
|--------------------------------------------------------------------------
| Start Upgrade
|--------------------------------------------------------------------------
*/

Route::post(
    '/laravel-upgrade',
    [
        LaravelUpgradeController::class,
        'upgrade'
    ]
)->name(
    'laravel-upgrade.upgrade'
);

/*
|--------------------------------------------------------------------------
| Retry Failed Upgrade
|--------------------------------------------------------------------------
*/

Route::post(
    '/laravel-upgrade/{upgrade}/retry',
    [
        LaravelUpgradeController::class,
        'retry'
    ]
)->name(
    'laravel-upgrade.retry'
);

/*
|--------------------------------------------------------------------------
| Upgrade Status
|--------------------------------------------------------------------------
*/

Route::get(
    '/laravel-upgrade/status/{upgrade}',
    [
        LaravelUpgradeController::class,
        'status'
    ]
)->name(
    'laravel-upgrade.status'
);

/*
|--------------------------------------------------------------------------
| Real-time Stream
|--------------------------------------------------------------------------
*/

Route::get(
    '/laravel-upgrade/stream/{upgrade}',
    [
        LaravelUpgradeController::class,
        'stream'
    ]
)->name(
    'laravel-upgrade.stream'
);

/*
|--------------------------------------------------------------------------
| Export CSV
|--------------------------------------------------------------------------
*/

Route::get(
    '/laravel-upgrade/export/csv',
    [
        LaravelUpgradeController::class,
        'export'
    ]
)->name(
    'laravel-upgrade.export'
);

/*
|--------------------------------------------------------------------------
| Delete History
|--------------------------------------------------------------------------
*/

Route::delete(
    '/laravel-upgrade/{upgrade}',
    [
        LaravelUpgradeController::class,
        'destroy'
    ]
)->name(
    'laravel-upgrade.destroy'
);
