<?php
/**
 * challenge.php — Demo decrypt + PIN entry page to complete a login.
 */

declare(strict_types=1);
session_start();

require __DIR__ . '/../PgpTwoFactor.php';

$encrypted = $_SESSION['pgp2fa_login_encrypted'] ?? null;
$error = null;

if (!$encrypted) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pgp = new PgpTwoFactor();
    $result = $pgp->verifyLoginChallenge($_POST['pin'] ?? '');

    if ($result['ok']) {
        $email = $_SESSION['pgp2fa_login_email'] ?? 'unknown';
        unset($_SESSION['pgp2fa_login_encrypted'], $_SESSION['pgp2fa_login_email']);
        echo "✅ 2FA passed. Login completed for {$email}.";
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>2FA Challenge — Demo</title></head>
<body style="font-family:system-ui,sans-serif;max-width:500px;margin:60px auto;">
<h2>🔑 Two-Factor Authentication</h2>
<p>Decrypt the following message using your PGP key:</p>
<textarea readonly rows="10" style="width:100%;font-family:monospace;font-size:12px;"><?= htmlspecialchars($encrypted) ?></textarea>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
  <label>PIN hasil decrypt</label><br>
  <input type="text" name="pin" autofocus required style="width:100%;padding:8px;margin:6px 0;">
  <button type="submit" style="padding:8px 16px;">Verify</button>
</form>
</body>
</html>
