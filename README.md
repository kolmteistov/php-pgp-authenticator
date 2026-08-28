# PGP-Based Two-Factor Authentication

A two-factor authentication scheme that does not rely on TOTP (Google Authenticator) or OTP delivered by SMS/email. Instead, the server encrypts a one-time PIN with the user's **PGP public key**, and the user can only retrieve that PIN by decrypting it with their own private key (for example, via GnuPG or Kleopatra).

This was originally built as part of a Laravel-based admin panel deployed as a Tor Hidden Service. This repository contains only the 2FA feature, extracted and cleaned up so it can be reused, studied, or dropped into another project. It includes both the original Laravel implementation and a framework-agnostic PHP port.

## Why PGP Instead of TOTP?

| | TOTP (Authenticator App) | PGP 2FA |
|---|---|---|
| Secret is held by | The user's phone (authenticator app) | The user's private key (can live on a hardware token, smartcard, or an offline machine) |
| If the server is compromised | The TOTP secret still needs to be protected at rest in the database | The server only ever stores a **public key** — there is no secret to steal from the database |
| Best suited for | General-purpose use | High-threat-model scenarios: sensitive admin panels, access over Tor, or users who are already comfortable with GPG |

The trade-off is user experience: the user needs a GPG key and decryption software, so this is not meant to replace TOTP universally — it fits scenarios where the user base is security-conscious by nature.

## How It Works

```
┌──────────┐   1. Login (email + password)   ┌──────────┐
│  User    │ ───────────────────────────────▶│  Server  │
└──────────┘                                  └────┬─────┘
                                                    │ 2. Password valid & 2FA enabled?
                                                    ▼
                                     Generate a random PIN (8 hex chars)
                                     Encrypt(PIN) using the user's PGP public key
                                                    │
     ┌──────────────────────────────────────────────┘
     ▼
3. Display the encrypted message (armored PGP block)
     │
     ▼
4. The user decrypts it with their private key (GPG CLI, Kleopatra, etc.)
     │
     ▼
5. The user submits the decrypted PIN
     │
     ▼
6. The server compares hash(submitted PIN) against hash(original PIN) stored in the session
     │
     ├─ Match     → login completes, authenticated session is created
     └─ No match  → rejected; the plaintext PIN is never exposed again
```

Design notes:
- **The PIN is never stored in plaintext.** Only `sha256(PIN)` is kept in the session, along with an expiry timestamp.
- **The private key never touches the server.** The server only ever holds the public key. Decryption happens entirely on the user's side.
- Every challenge PIN is random and single-use, and expires after 10 minutes (login) or 15 minutes (initial key verification).
- Key import and encryption use the `gnupg` PHP extension (a binding to GPGME), not a shell-out to the `gpg` CLI — which avoids command-injection risk entirely.

## Features

- PGP public key setup from the user's profile page, with a mandatory one-time verification step (the user must decrypt a PIN before the key is accepted and 2FA can be enabled).
- Supports RSA, DSA, and ECDSA (ed25519) — anything GPGME supports, as long as the key has an encryption subkey.
- Per-user toggle to enable/disable 2FA.
- Key removal at any time (automatically disables 2FA).
- Domain events (`TwoFactorEnabled`, `TwoFactorDisabled`, `TwoFactorKeyRemoved`) so your own application can log or notify without modifying this code.
- Session-based challenges with expiry and single-use PINs (hash-only storage, no replay).

## Screenshots

<p align="center">
  <img src="https://github.com/user-attachments/assets/5706888f-1f4a-43c7-adb2-41e0d7dd3f69" width="32%" />
  <img src="https://github.com/user-attachments/assets/b61053bd-5ebd-4bea-a618-4fe21e9a4cba" width="32%" />
  <img src="https://github.com/user-attachments/assets/2781171d-8051-497e-bfbf-f56cac0e53c6" width="32%" />
</p>



- 2FA setup page (PGP Key / Verification / 2FA Status)
- Login-time challenge page (encrypted message + PIN input)
- Example decryption via Kleopatra (GPG4Win)

## Repository Structure

```
laravel-pgp-2fa/
├── README.md
├── src/
│   ├── laravel/                                   Laravel 12 / PHP 8.4 implementation
│   │   ├── config/pgp2fa.php                       Package configuration (site name, TTLs)
│   │   ├── app/Services/PgpService.php             GPGME wrapper: import, validate, encrypt
│   │   ├── app/Http/Controllers/TwoFaController.php   Key setup, verification, enable/disable
│   │   ├── app/Http/Controllers/Concerns/
│   │   │   └── HandlesPgpChallenge.php             Trait to drop into your own AuthController
│   │   ├── app/Events/                             TwoFactorEnabled / Disabled / KeyRemoved
│   │   ├── database/migrations/..._add_2fa_to_users_table.php
│   │   ├── resources/views/auth/two_fa.blade.php   Login-time challenge page
│   │   ├── resources/views/two_fa/setup.blade.php
│   │   ├── resources/views/two_fa/verify_key.blade.php
│   │   └── routes/two-fa-routes-snippet.php
│   └── php-native/                                 Framework-agnostic port
│       ├── PgpTwoFactor.php                        Core class, reusable anywhere
│       └── example/                                Runnable demo (JSON-file storage)
```

## Installation & Integration (Laravel)

### 1. Requirements

- PHP 8.4 with the `gnupg` extension enabled (`pecl install gnupg`, which requires `libgpgme-dev` on the system)
- GnuPG installed on the server (`gpg --version`)
- Laravel 10/11/12

Check that the extension is loaded:
```bash
php -m | grep gnupg
```

### 2. Copy the files into your Laravel project

```bash
cp src/laravel/config/pgp2fa.php                          config/
cp src/laravel/app/Services/PgpService.php                 app/Services/
cp src/laravel/app/Http/Controllers/TwoFaController.php    app/Http/Controllers/
cp -r src/laravel/app/Http/Controllers/Concerns             app/Http/Controllers/
cp -r src/laravel/app/Events                                app/
cp src/laravel/database/migrations/*.php                    database/migrations/
cp src/laravel/resources/views/auth/two_fa.blade.php        resources/views/auth/
cp -r src/laravel/resources/views/two_fa                    resources/views/
```

### 3. Run the migration

The migration adds three columns to the `users` table:

```php
$table->boolean('two_fa_enabled')->default(false);
$table->text('pgp_public_key')->nullable();
$table->boolean('pgp_verified')->default(false);
```

```bash
php artisan migrate
```

### 4. Register the routes

Paste the contents of `src/laravel/routes/two-fa-routes-snippet.php` into your `routes/web.php`. The `two-fa/challenge` routes must stay **outside** the `auth` middleware group, since the user is not yet fully authenticated at that point — the setup/toggle/remove routes, on the other hand, require `auth`.

### 5. Add the trait to your existing AuthController

Instead of replacing your login logic, `HandlesPgpChallenge` is a trait you attach to your existing `AuthController`. Your rate limiting, captcha, and login logging stay exactly as they are — only one new branch is added after the password check:

```php
use App\Http\Controllers\Concerns\HandlesPgpChallenge;

class AuthController extends Controller
{
    use HandlesPgpChallenge;

    public function login(Request $request)
    {
        // ...your existing validation / rate limiting / captcha...

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if ($user->two_fa_enabled && $user->pgp_verified && $user->pgp_public_key) {
                return $this->startTwoFaChallenge($user);
            }

            // ...your existing "login succeeded, no 2FA" logic...
        }

        // ...your existing failure logic...
    }
}
```

If you want to hook into your own "login succeeded" logic after 2FA passes (e.g. writing to a `login_logs` table), override the `afterTwoFaSuccess()` method that the trait provides — no need to edit the trait itself.

### 6. Configuration

`config/pgp2fa.php` controls the site name shown inside the encrypted message and the challenge/verification expiry windows. Override any value via `.env`:

```
PGP_2FA_SITE_NAME="My Application"
```

Once this is wired up, users can visit `/two-fa/setup` to add and verify their PGP key.

## Installation & Integration (PHP Native / No Framework)

See [`src/php-native/README.md`](src/php-native/README.md) for the full guide. In short:

```bash
cd src/php-native/example
php -S localhost:8000
# open http://localhost:8000/setup.php
```

`PgpTwoFactor` is deliberately framework-agnostic — it only requires `$_SESSION` to already be started, with zero dependency on Laravel. It can be dropped into CodeIgniter, a WordPress plugin, or plain PHP without modification.

## Security Notes

- Never let concurrent requests share the same `GNUPGHOME` under high concurrency — make sure each process/worker uses an isolated keyring directory (see how `PgpService` / `PgpTwoFactor` set `GNUPGHOME` per instance).
- Rate-limit the challenge and verification endpoints so the 8-character hex PIN cannot be brute-forced, even though it already expires after 10 minutes — apply this at the route/middleware level.
- Any submitted public key **must be validated for format and the presence of an encryption subkey** before being stored (`isValidPublicKey()` handles format validation; adding an explicit encryption-subkey check is recommended for stricter environments).
- This is an additional factor, not a replacement for a strong password policy and login rate limiting.

## License

MIT — use, modify, and republish freely for your own projects or portfolio.
