<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CountriesController;
use App\Http\Controllers\Api\V1\EndlessProgressController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlayerStateController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->middleware('throttle:api')->name('api.v1.health');
    Route::get('/company', fn () => response()->json([
        'success' => true,
        'data' => config('company'),
    ]))->middleware('throttle:api')->name('api.v1.company');
    Route::get('/registration/countries', CountriesController::class)->middleware('throttle:api')->name('api.v1.registration.countries');
    Route::post('/register', RegisterController::class)->middleware('throttle:auth')->name('api.v1.register');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('api.v1.email.verify');

    Route::post('/login', LoginController::class)->middleware('throttle:auth')->name('api.v1.login');
    Route::get('/catalog', CatalogController::class)->middleware('throttle:api')->name('api.v1.catalog');
    Route::post('/payments/webhooks/{provider}', [PaymentController::class, 'webhook'])
        ->middleware('throttle:webhook')
        ->name('api.v1.payments.webhook');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('/logout', LogoutController::class)->name('api.v1.logout');
        Route::get('/me', [PlayerStateController::class, 'show'])->name('api.v1.me');
        Route::get('/me/wallet', [PlayerStateController::class, 'wallet'])->name('api.v1.me.wallet');
        Route::get('/me/inventory', [PlayerStateController::class, 'inventory'])->name('api.v1.me.inventory');
        Route::put('/me/loadout', [PlayerStateController::class, 'updateLoadout'])->name('api.v1.me.loadout');
        Route::post('/me/towers/upgrade', [PlayerStateController::class, 'upgradeTower'])->name('api.v1.me.towers.upgrade');
        Route::post('/me/chests/open', [PlayerStateController::class, 'openChest'])->name('api.v1.me.chests.open');
        Route::post('/me/wallet/topup', [PlayerStateController::class, 'topup'])->name('api.v1.me.wallet.topup');
        Route::get('/me/game/progress', [EndlessProgressController::class, 'show'])->name('api.v1.me.game.progress');
        Route::post('/me/game/checkpoints', [EndlessProgressController::class, 'checkpoint'])->name('api.v1.me.game.checkpoints');
        Route::get('/me/orders', [OrderController::class, 'index'])->name('api.v1.me.orders');
        Route::post('/me/orders', [OrderController::class, 'store'])->name('api.v1.me.orders.store');
        Route::post('/me/orders/{order}/sandbox-pay', [PaymentController::class, 'sandboxPay'])
            ->name('api.v1.me.orders.sandbox-pay');
        Route::get('/me/invoices/{invoice}', [InvoiceController::class, 'show'])
            ->name('api.v1.me.invoices.show');
    });
});
