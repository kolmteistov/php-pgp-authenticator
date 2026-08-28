<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\PgpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HandlesPgpChallenge
 *
 * Drop this trait into your existing AuthController — it adds the
 * "sign in with PGP" step without touching your existing login logic
 * (rate limiting, captcha, login logging, etc. stay exactly as they are).
 *
 * Usage in your AuthController:
 *
 *   use App\Http\Controllers\Concerns\HandlesPgpChallenge;
 *
 *   class AuthController extends Controller
 *   {
 *       use HandlesPgpChallenge;
 *
 *       public function login(Request $request)
 *       {
 *           // ...your existing validation / rate limiting / captcha...
 *
 *           if (Auth::attempt($credentials)) {
 *               $user = Auth::user();
 *
 *               if ($user->two_fa_enabled && $user->pgp_verified && $user->pgp_public_key) {
 *                   return $this->startTwoFaChallenge($user);
 *               }
 *
 *               // ...your existing "login succeeded, no 2FA" logic...
 *           }
 *
 *           // ...your existing failure logic...
 *       }
 *   }
 *
 * Then register the two routes below (outside the "auth" middleware group,
 * since the user is not fully authenticated yet at this point):
 *
 *   Route::get('/two-fa/challenge',  [AuthController::class, 'showTwoFaChallenge'])->name('two_fa.challenge');
 *   Route::post('/two-fa/challenge', [AuthController::class, 'verifyTwoFa'])->name('two_fa.verify');
 */
trait HandlesPgpChallenge
{
    /**
     * Call this right after Auth::attempt() succeeds, instead of finishing
     * the login yourself, when the user has 2FA enabled.
     */
    protected function startTwoFaChallenge(User $user)
    {
        // The user matched their password, but we don't want a full
        // authenticated session yet — log them back out and remember
        // who they are via a short-lived, unauthenticated session value.
        Auth::logout();

        session([
            'two_fa_user_id' => $user->id,
            'two_fa_expires' => now()->addMinutes(config('pgp2fa.login_challenge_ttl', 10))->timestamp,
        ]);

        return redirect()->route('two_fa.challenge');
    }

    /**
     * GET /two-fa/challenge — generates a fresh PIN, encrypts it with the
     * user's public key, and shows the decrypt-and-enter-PIN form.
     */
    public function showTwoFaChallenge()
    {
        if (!session('two_fa_user_id')) {
            return redirect()->route('login');
        }

        if (now()->timestamp > session('two_fa_expires', 0)) {
            session()->forget(['two_fa_user_id', 'two_fa_expires', 'two_fa_encrypted', 'two_fa_pin_hash']);
            return redirect()->route('login')->withErrors([
                'email' => 'Your 2FA session has expired. Please log in again.',
            ]);
        }

        $user = User::find(session('two_fa_user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $pgp = new PgpService();
        $pin = PgpService::generatePin(8);

        $encryptedMessage = $pgp->encrypt(
            PgpService::buildVerificationMessage($pin, 'login'),
            $user->pgp_public_key
        );

        if (!$encryptedMessage) {
            return redirect()->route('login')->withErrors([
                'email' => 'Could not generate the 2FA challenge. Please contact an administrator.',
            ]);
        }

        session([
            'two_fa_pin_hash'  => hash('sha256', $pin),
            'two_fa_encrypted' => $encryptedMessage,
        ]);

        return view('auth.two_fa', compact('encryptedMessage'));
    }

    /**
     * POST /two-fa/challenge — validates the decrypted PIN and, if correct,
     * finishes the authenticated session.
     */
    public function verifyTwoFa(Request $request)
    {
        if (!session('two_fa_user_id')) {
            return redirect()->route('login');
        }

        if (now()->timestamp > session('two_fa_expires', 0)) {
            session()->forget(['two_fa_user_id', 'two_fa_expires', 'two_fa_encrypted', 'two_fa_pin_hash']);
            return redirect()->route('login')->withErrors([
                'email' => 'Your 2FA session has expired. Please log in again.',
            ]);
        }

        $request->validate(['pin' => 'required|string']);

        $submittedPin = trim($request->pin);
        $storedHash   = session('two_fa_pin_hash');

        if (!$storedHash || !hash_equals($storedHash, hash('sha256', $submittedPin))) {
            return back()->withErrors(['pin' => 'Incorrect PIN. Please try again.']);
        }

        $user = User::find(session('two_fa_user_id'));

        session()->forget(['two_fa_user_id', 'two_fa_expires', 'two_fa_encrypted', 'two_fa_pin_hash']);

        Auth::login($user);
        $request->session()->regenerate();

        // Override this method in your AuthController if you want to hook
        // into your own "login succeeded" logic (e.g. write to login_logs).
        return $this->afterTwoFaSuccess($user, $request);
    }

    /**
     * Called once 2FA has passed and the authenticated session has been
     * created. Override this in your own AuthController — by default it
     * just redirects to the dashboard route.
     */
    protected function afterTwoFaSuccess(User $user, Request $request)
    {
        return redirect()->intended(route('dashboard'));
    }
}
