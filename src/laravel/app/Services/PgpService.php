<?php

namespace App\Services;

/**
 * PgpService
 *
 * Wraps the ext-gnupg extension to handle PGP key import, validation and
 * message encryption for the 2FA flow. Depends only on the "gnupg" PHP
 * extension and the config/pgp2fa.php file — no other app models required.
 */
class PgpService
{
    protected \gnupg $gnupg;
    protected string $homedir;

    public function __construct()
    {
        // Isolated keyring per process/request so keys never accumulate
        // or leak between users on the same server.
        $this->homedir = storage_path('app/gnupg');

        if (!is_dir($this->homedir)) {
            mkdir($this->homedir, 0700, true);
        }

        putenv('GNUPGHOME=' . $this->homedir);

        $this->gnupg = new \gnupg();
        $this->gnupg->seterrormode(\gnupg::ERROR_EXCEPTION);
    }

    /**
     * Import an armored public key into the temporary keyring.
     *
     * @return string|null The key fingerprint, or null if the import failed.
     */
    public function importKey(string $publicKey): ?string
    {
        try {
            $result = $this->gnupg->import($publicKey);
            return $result['fingerprint'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function isValidPublicKey(string $publicKey): bool
    {
        return $this->importKey($publicKey) !== null;
    }

    /**
     * Encrypt a plaintext message for the given armored public key.
     *
     * @return string|null The armored PGP message, or null on failure.
     */
    public function encrypt(string $message, string $publicKey): ?string
    {
        try {
            $fingerprint = $this->importKey($publicKey);
            if (!$fingerprint) {
                return null;
            }

            $this->gnupg->addencryptkey($fingerprint);
            $encrypted = $this->gnupg->encrypt($message);
            $this->gnupg->clearencryptkeys();

            return $encrypted ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build the plaintext message that will be encrypted and shown to the user.
     *
     * $context is either "login" (2FA challenge at sign-in) or "verify"
     * (one-time confirmation when a new key is added).
     */
    public static function buildVerificationMessage(string $pin, string $context = 'login'): string
    {
        $siteName = config('pgp2fa.site_name', config('app.name', 'My Application'));

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

    public static function generatePin(int $length = 8): string
    {
        return bin2hex(random_bytes((int) ($length / 2)));
    }
}
