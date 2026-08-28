<?php
/**
 * setup.php — Demo PGP 2FA setup page (native PHP, no framework).
 *
 * Flow:
 *   1. The user enters an email and pastes a public key -> submitted here
 *   2. If valid, show the encrypted message + a form to enter the decrypted PIN
 *   3. Submit the PIN -> if it matches, the key is saved as "verified" in the demo store
 */

declare(strict_types=1);
session_start();

require __DIR__ . '/../PgpTwoFactor.php';
require __DIR__ . '/store.php';

const SITE_NAME = 'PGP 2FA Demo (Native PHP)';

$pgp = new PgpTwoFactor();
$message = null;
$encrypted = $_SESSION['pgp2fa_verify_encrypted'] ?? null;
$pendingEmail = $_SESSION['pgp2fa_pending_email'] ?? null;

// Step 1: submit public key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_key') {
    $email = trim($_POST['email'] ?? '');
    $key   = trim($_POST['public_key'] ?? '');

    $result = $pgp->beginKeyVerification($key, SITE_NAME);

    if (!$result['ok']) {
        $message = '❌ ' . $result['error'];
    } else {
        $_SESSION['pgp2fa_verify_encrypted'] = $result['encrypted'];
        $_SESSION['pgp2fa_pending_email']    = $email;
        $encrypted = $result['encrypted'];
        $pendingEmail = $email;
        $message = '✅ Key accepted. Decrypt the message below, then enter the PIN.';
    }
}

// Step 2: confirm PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_pin') {
    $result = $pgp->confirmKeyVerification($_POST['pin'] ?? '');

    if (!$result['ok']) {
        $message = '❌ ' . $result['error'];
        $encrypted = $_SESSION['pgp2fa_verify_encrypted'] ?? null;
    } else {
        $email = $pendingEmail ?? 'unknown@example.com';
        UserStore::save([
            'email'          => $email,
            'pgp_public_key' => $result['public_key'],
            'pgp_verified'   => true,
            'two_fa_enabled' => true,
        ]);
        unset($_SESSION['pgp2fa_pending_email']);
        $message = "✅ PGP key for {$email} verified successfully — 2FA is now active.";
        $encrypted = null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Setup PGP 2FA — Demo</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; }
  textarea { width: 100%; font-family: monospace; font-size: 12px; }
  .msg { padding: 10px; border-radius: 6px; background: #f2f2f2; margin-bottom: 16px; }
  label { display: block; margin-top: 12px; font-weight: 600; }
  input, textarea { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; }
  button { margin-top: 12px; padding: 8px 16px; }
</style>
</head>
<body>
<h2>🔐 Setup PGP 2FA (Native PHP Demo)</h2>

<?php if ($message): ?>
  <div class="msg"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!$encrypted): ?>
  <form method="post">
    <input type="hidden" name="action" value="submit_key">
    <label>Email</label>
    <input type="email" name="email" required>
    <label>PGP Public Key (armored)</label>
    <textarea name="public_key" rows="10" required placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----"></textarea>
    <button type="submit">Save &amp; Verify</button>
  </form>
<?php else: ?>
  <label>Encrypted message (decrypt using GPG/Kleopatra)</label>
  <textarea readonly rows="10"><?= htmlspecialchars($encrypted) ?></textarea>
  <form method="post">
    <input type="hidden" name="action" value="confirm_pin">
    <label>PIN hasil decrypt</label>
    <input type="text" name="pin" autofocus required>
    <button type="submit">Verify PIN</button>
  </form>
<?php endif; ?>

</body>
</html>
