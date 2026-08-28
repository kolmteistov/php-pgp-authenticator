<?php
/**
 * Paste this snippet into your routes/web.php.
 * These are only the routes relevant to the PGP 2FA feature.
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TwoFaController;

// 2FA challenge — reached after a successful password check, before the
// authenticated session is created. Must stay outside the "auth" middleware.
Route::get('/two-fa/challenge',  [AuthController::class, 'showTwoFaChallenge'])->name('two_fa.challenge');
Route::post('/two-fa/challenge', [AuthController::class, 'verifyTwoFa'])->name('two_fa.verify');

// 2FA setup & management — requires an authenticated session.
Route::middleware('auth')->group(function () {
    Route::get('/two-fa/setup',        [TwoFaController::class, 'setup'])->name('two_fa.setup');
    Route::post('/two-fa/save-key',    [TwoFaController::class, 'saveKey'])->name('two_fa.save_key');
    Route::get('/two-fa/verify-key',   [TwoFaController::class, 'verifyKeyPage'])->name('two_fa.verify_key');
    Route::post('/two-fa/verify-key',  [TwoFaController::class, 'verifyKey'])->name('two_fa.verify_key_post');
    Route::post('/two-fa/toggle',      [TwoFaController::class, 'toggle'])->name('two_fa.toggle');
    Route::post('/two-fa/remove-key',  [TwoFaController::class, 'removeKey'])->name('two_fa.remove_key');
});
