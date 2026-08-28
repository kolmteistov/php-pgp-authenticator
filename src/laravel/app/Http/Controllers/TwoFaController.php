<?php

namespace App\Http\Controllers;

use App\Events\TwoFactorDisabled;
use App\Events\TwoFactorEnabled;
use App\Events\TwoFactorKeyRemoved;
use App\Services\PgpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * TwoFaController
 *
 * Handles PGP key setup, one-time key verification, and enabling/disabling
 * 2FA from the user's profile. The login-time challenge itself lives in
 * app/Http/Controllers/Concerns/HandlesPgpChallenge.php, which your own
 * AuthController should use.
 *
 * This controller intentionally has no dependency on audit-log or
 * notification code from your own application. If you want to record these
 * actions (e.g. to an audit log), listen for the TwoFactorEnabled,
 * TwoFactorDisabled and TwoFactorKeyRemoved events instead of editing this
 * file — see the "Wiring it into your own app" section of the README.
 */
class TwoFaController extends Controller
{
    /**
     * Show the 2FA setup page in the user's profile.
     */
    public function setup()
    {
        $user = Auth::user();
        return view('two_fa.setup', compact('user'));
    }

    /**
     * Store a new PGP public key and start the one-time verification challenge.
     */
    public function saveKey(Request $request)
    {
        $request->validate([
            'pgp_public_key' => 'required|string',
        ]);

        $pgp = new PgpService();
        $key = trim($request->pgp_public_key);

        if (!$pgp->isValidPublicKey($key)) {
            return back()->withErrors([
                'pgp_public_key' => 'This does not look like a valid PGP public key.',
            ])->withInput();
        }

        // Key is stored but not yet marked as verified.
        $user = Auth::user();
        $user->update([
            'pgp_public_key' => $key,
            'pgp_verified'   => false,
            'two_fa_enabled' => false,
        ]);

        $pin = PgpService::generatePin(8);
        $encryptedMessage = $pgp->encrypt(
            PgpService::buildVerificationMessage($pin, 'verify'),
            $key
        );

        session([
            'pgp_verify_pin_hash'  => hash('sha256', $pin),
            'pgp_verify_encrypted' => $encryptedMessage,
            'pgp_verify_expires'   => now()->addMinutes(config('pgp2fa.key_verification_ttl', 15))->timestamp,
        ]);

        return redirect()->route('two_fa.verify_key');
    }

    /**
     * Show the "decrypt this and enter the PIN" page for a freshly added key.
     */
    public function verifyKeyPage()
    {
        if (!session('pgp_verify_pin_hash')) {
            return redirect()->route('two_fa.setup');
        }

        $encryptedMessage = session('pgp_verify_encrypted');
        return view('two_fa.verify_key', compact('encryptedMessage'));
    }

    /**
     * Confirm the PIN decrypted by the user and mark the key as verified.
     */
    public function verifyKey(Request $request)
    {
        if (!session('pgp_verify_pin_hash')) {
            return redirect()->route('two_fa.setup');
        }

        if (now()->timestamp > session('pgp_verify_expires', 0)) {
            session()->forget(['pgp_verify_pin_hash', 'pgp_verify_encrypted', 'pgp_verify_expires']);
            return redirect()->route('two_fa.setup')->withErrors([
                'pin' => 'This verification session has expired. Please try again.',
            ]);
        }

        $request->validate(['pin' => 'required|string']);

        $submittedPin = trim($request->pin);
        $storedHash   = session('pgp_verify_pin_hash');

        if (!hash_equals($storedHash, hash('sha256', $submittedPin))) {
            return back()->withErrors(['pin' => 'Incorrect PIN. Make sure you decrypted the message correctly.']);
        }

        $user = Auth::user();
        $user->update(['pgp_verified' => true]);

        session()->forget(['pgp_verify_pin_hash', 'pgp_verify_encrypted', 'pgp_verify_expires']);

        return redirect()->route('two_fa.setup')
            ->with('success', 'Your PGP key has been verified.');
    }

    /**
     * Enable or disable 2FA for the current user.
     */
    public function toggle(Request $request)
    {
        $user = Auth::user();

        if (!$user->pgp_verified || !$user->pgp_public_key) {
            return back()->withErrors([
                'error' => 'Verify your PGP key before enabling 2FA.',
            ]);
        }

        $user->update(['two_fa_enabled' => !$user->two_fa_enabled]);

        event($user->two_fa_enabled ? new TwoFactorEnabled($user) : new TwoFactorDisabled($user));

        $status = $user->two_fa_enabled ? 'enabled' : 'disabled';
        return back()->with('success', "Two-factor authentication has been {$status}.");
    }

    /**
     * Remove the stored PGP key and disable 2FA.
     */
    public function removeKey()
    {
        $user = Auth::user();

        $user->update([
            'pgp_public_key' => null,
            'pgp_verified'   => false,
            'two_fa_enabled' => false,
        ]);

        event(new TwoFactorKeyRemoved($user));

        return redirect()->route('two_fa.setup')
            ->with('success', 'Your PGP key has been removed.');
    }
}
