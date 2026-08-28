<?php
/**
 * PgpTwoFactor.php
 *
 * Framework-agnostic implementation of PGP-based Two-Factor Authentication,
 * ported from the Laravel implementation (app/Services/PgpService.php +
 * TwoFaController.php) so it can be dropped into a plain PHP or legacy
 * project that doesn't use Laravel.
 *
 * Requirements:
 *   - ext-gnupg (pecl install gnupg, requires libgpgme-dev)
 *   - A PHP session already started (session_start())
 *
 * High-level flow:
 *   1. beginKeyVerification()  -> user submits a PGP public key; it is held
 *      temporarily and an encrypted PIN is generated for one-time verification.
 *   2. confirmKeyVerification()-> user submits the decrypted PIN; if it
 *      matches, the key is accepted as valid and verified.
 *   3. beginLoginChallenge()   -> on login, generate a fresh PIN and encrypt
 *      it with the user's stored public key.
 *   4. verifyLoginChallenge()  -> user submits the decrypted PIN; a match
 *      completes the 2FA step.
 *
 * Security notes:
 *   - The PIN is NEVER stored in plaintext — only its SHA-256 hash is kept
 *     in the session.
 *   - The public key storage (DB/file/etc.) is the caller's responsibility,
 *     not this class's.
 *   - GNUPGHOME points to a directory isolated per process so keyrings don't
 *     accumulate or leak between users on the same server.
 */

declare(strict_types=1);

final class PgpTwoFactor
{
    private \gnupg $gnupg;
    private string $homedir;

    public function __construct(?string $homedir = null)
    {
        $this->homedir = $homedir ?? sys_get_temp_dir() . '/pgp2fa-' . session_id();

        if (!is_dir($this->homedir)) {
            mkdir($this->homedir, 0700, true);
        }

        putenv('GNUPGHOME=' . $this->homedir);

        $this->gnupg = new \gnupg();
        $this->gnupg->seterrormode(\gnupg::ERROR_EXCEPTION);
    }

    /**
     * Import a public key into the temporary keyring, returning its fingerprint if valid.
     */
    public function importKey(string $publicKeyArmored): ?string
    {
        try {
            $result = $this->gnupg->import($publicKeyArmored);
            return $result['fingerprint'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isValidPublicKey(string $publicKeyArmored): bool
    {
        return $this->importKey($publicKeyArmored) !== null;
    }

    /**
     * Encrypt a message with an armored public key. Returns null on failure.
     */
    public function encrypt(string $message, string $publicKeyArmored): ?string
    {
        try {
            $fingerprint = $this->importKey($publicKeyArmored);
            if (!$fingerprint) {
                return null;
            }
            $this->gnupg->addencryptkey($fingerprint);
            $encrypted = $this->gnupg->encrypt($message);
            $this->gnupg->clearencryptkeys();
            return $encrypted ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function generatePin(int $length = 8): string
    {
        return bin2hex(random_bytes(intdiv($length, 2)));
    }

    public static function buildVerificationMessage(string $siteName, string $pin, string $context = 'login'): string
    {
        if ($context === 'login') {
            return "{$siteName} - Two Factor Authentication\n\n" .
                   "PIN: {$pin}\n\n" .
                   "Enter this PIN to complete your login.\n" .
                   "Do not share this PIN with anyone.";
        }

        return "{$siteName} - PGP Key Verification\n\n" .
               "PIN: {$pin}\n\n" .
               "Enter this PIN to verify that your PGP key works correctly.";
    }

    // ── High-level flow, using $_SESSION directly (framework-agnostic) ─────

    /**
     * Step 1: submit a new public key and generate a challenge PIN to verify it.
     * Return array ['ok' => bool, 'encrypted' => ?string, 'error' => ?string]
     */
    public function beginKeyVerification(string $publicKeyArmored, string $siteName): array
    {
        if (!$this->isValidPublicKey($publicKeyArmored)) {
            return ['ok' => false, 'error' => 'Invalid PGP public key format.'];
        }

        $pin = self::generatePin(8);
        $encrypted = $this->encrypt(
            self::buildVerificationMessage($siteName, $pin, 'verify'),
            $publicKeyArmored
        );

        if (!$encrypted) {
            return ['ok' => false, 'error' => 'Failed to encrypt the verification message.'];
        }

        $_SESSION['pgp2fa_verify_pin_hash'] = hash('sha256', $pin);
        $_SESSION['pgp2fa_verify_expires']  = time() + 900; // 15 menit
        $_SESSION['pgp2fa_pending_key']     = $publicKeyArmored;

        return ['ok' => true, 'encrypted' => $encrypted];
    }

    /**
     * Step 2: submit the decrypted PIN to verify the key submitted in step 1.
     * Return ['ok' => bool, 'public_key' => ?string, 'error' => ?string]
     */
    public function confirmKeyVerification(string $submittedPin): array
    {
        if (empty($_SESSION['pgp2fa_verify_pin_hash'])) {
            return ['ok' => false, 'error' => 'No verification process is currently in progress.'];
        }

        if (time() > ($_SESSION['pgp2fa_verify_expires'] ?? 0)) {
            unset($_SESSION['pgp2fa_verify_pin_hash'], $_SESSION['pgp2fa_verify_expires'], $_SESSION['pgp2fa_pending_key']);
            return ['ok' => false, 'error' => 'Verification session expired. Please start over.'];
        }

        if (!hash_equals($_SESSION['pgp2fa_verify_pin_hash'], hash('sha256', trim($submittedPin)))) {
            return ['ok' => false, 'error' => 'Incorrect PIN.'];
        }

        $publicKey = $_SESSION['pgp2fa_pending_key'];
        unset($_SESSION['pgp2fa_verify_pin_hash'], $_SESSION['pgp2fa_verify_expires'], $_SESSION['pgp2fa_pending_key']);

        return ['ok' => true, 'public_key' => $publicKey];
    }

    /**
     * Step 3: start the 2FA challenge at login (call this after the password check passes).
     */
    public function beginLoginChallenge(string $userPublicKey, string $siteName): array
    {
        $pin = self::generatePin(8);
        $encrypted = $this->encrypt(
            self::buildVerificationMessage($siteName, $pin, 'login'),
            $userPublicKey
        );

        if (!$encrypted) {
            return ['ok' => false, 'error' => 'Failed to generate the 2FA challenge.'];
        }

        $_SESSION['pgp2fa_login_pin_hash'] = hash('sha256', $pin);
        $_SESSION['pgp2fa_login_expires']  = time() + 600; // 10 menit

        return ['ok' => true, 'encrypted' => $encrypted];
    }

    /**
     * Step 4: verify the login PIN.
     */
    public function verifyLoginChallenge(string $submittedPin): array
    {
        if (empty($_SESSION['pgp2fa_login_pin_hash'])) {
            return ['ok' => false, 'error' => 'No active 2FA session.'];
        }

        if (time() > ($_SESSION['pgp2fa_login_expires'] ?? 0)) {
            unset($_SESSION['pgp2fa_login_pin_hash'], $_SESSION['pgp2fa_login_expires']);
            return ['ok' => false, 'error' => '2FA session expired. Please log in again.'];
        }

        $match = hash_equals($_SESSION['pgp2fa_login_pin_hash'], hash('sha256', trim($submittedPin)));
        unset($_SESSION['pgp2fa_login_pin_hash'], $_SESSION['pgp2fa_login_expires']);

        return $match ? ['ok' => true] : ['ok' => false, 'error' => 'Incorrect PIN.'];
    }

    /**
     * Clean up the temporary keyring. Call this at the end of the request if needed.
     */
    public function cleanup(): void
    {
        $files = glob($this->homedir . '/*');
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($this->homedir);
    }
}
