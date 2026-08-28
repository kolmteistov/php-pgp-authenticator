# PGP 2FA — Native PHP Port

A framework-agnostic port of the Laravel implementation found in this repository. Suitable for plain PHP projects, CodeIgniter, or legacy CMS codebases that do not use Laravel.

## Requirements

- PHP 8.0+
- The `gnupg` extension enabled (`php -m | grep gnupg`)
- GnuPG installed on the server

## Files

| File | Purpose |
|---|---|
| `PgpTwoFactor.php` | Core class — reusable, only requires `$_SESSION` to be started |
| `example/setup.php` | Demo: submit and verify a public key |
| `example/login.php` | Demo: login flow that triggers the 2FA challenge |
| `example/challenge.php` | Demo: decrypt-and-enter-PIN page |
| `example/store.php` | Placeholder storage (JSON file) for the demo — replace with your own database |

## Running the Demo

```bash
cd src/php-native/example
php -S localhost:8000
```

Open `http://localhost:8000/setup.php`, paste your public key, decrypt the resulting message with GPG, and enter the PIN. Once verified, try logging in at `http://localhost:8000/login.php`.

## Integrating Into Your Own Project

```php
session_start();
require 'PgpTwoFactor.php';

$pgp = new PgpTwoFactor();

// Step 1: submit a public key
$result = $pgp->beginKeyVerification($publicKeyArmored, 'My Application');
if ($result['ok']) {
    // display $result['encrypted'] to the user
}

// Step 2: the user submits the decrypted PIN
$confirm = $pgp->confirmKeyVerification($_POST['pin']);
if ($confirm['ok']) {
    // persist $confirm['public_key'] against the user, mark as verified
}

// Step 3: at login, once the password has been verified
$challenge = $pgp->beginLoginChallenge($user['pgp_public_key'], 'My Application');
// display $challenge['encrypted']

// Step 4: the user submits the decrypted PIN
$verify = $pgp->verifyLoginChallenge($_POST['pin']);
if ($verify['ok']) {
    // 2FA passed — create the authenticated session
}
```

The class intentionally does not persist anything to a database — storage (database, file, or otherwise) is left to the caller so it can be plugged into any storage layer.

## Notes

- `example/` is for demonstration only. Its JSON-based storage (`users.json`) is **not suitable for production** — it is prone to race conditions under concurrent access. Replace it with a real database.
- The password check in `login.php` is intentionally omitted so the example stays focused on the 2FA flow. Add a normal `password_verify()` step before reaching the 2FA branch in a real application.
