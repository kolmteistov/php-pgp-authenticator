<?php
/**
 * login.php — Demo login that triggers the PGP 2FA challenge.
 * The password check is skipped here (demo only) — the password is assumed
 * correct as long as the email is registered in store.json. In a real
 * project, verify the password hash first as usual.
 */

declare(strict_types=1);
session_start();

require __DIR__ . '/../PgpTwoFactor.php';
require __DIR__ . '/store.php';

const SITE_NAME = 'PGP 2FA Demo (Native PHP)';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $user  = UserStore::find($email);

    if (!$user) {
        $error = 'User not found. Set up a key at setup.php first.';
    } elseif (!empty($user['two_fa_enabled']) && !empty($user['pgp_verified'])) {
        $pgp = new PgpTwoFactor();
        $result = $pgp->beginLoginChallenge($user['pgp_public_key'], SITE_NAME);

        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $_SESSION['pgp2fa_login_email'] = $email;
            $_SESSION['pgp2fa_login_encrypted'] = $result['encrypted'];
            header('Location: challenge.php');
            exit;
        }
    } else {
        // Tidak ada 2FA, langsung "login"
        echo "Login succeeded without 2FA for {$email} (demo).";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Login — Demo</title></head>
<body style="font-family:system-ui,sans-serif;max-width:400px;margin:60px auto;">
<h2>Login (Demo)</h2>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
  <label>Email</label><br>
  <input type="email" name="email" required style="width:100%;padding:8px;margin:6px 0;">
  <button type="submit" style="padding:8px 16px;">Login</button>
</form>
<p><a href="setup.php">Don't have a key yet? Set one up here</a></p>
</body>
</html>
