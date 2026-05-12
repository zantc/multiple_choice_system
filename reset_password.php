<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = getDB();
$message = '';
$isValidToken = false;
$token = '';

// Validate token từ URL
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $stmt  = $pdo->prepare("SELECT email FROM users WHERE reset_token = ? AND reset_expires_at >= NOW()");
    $stmt->execute([$token]);
    $user  = $stmt->fetch();

    if ($user) {
        $isValidToken = true;
    } else {
        $message = "<div class='alert error'>Recovery link is invalid or has expired!</div>";
    }
}

// Xử lý submit form đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // verify_csrf() is run automatically if action is posted, let's explicitly verify if action wasn't set:
    if (!isset($_POST['action'])) {
        verify_csrf();
    }

    $new_password     = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $post_token       = $_POST['reset_token']      ?? '';

    if (strlen($new_password) < 6) {
        $message      = "<div class='alert error'>Password must be at least 6 characters!</div>";
        $isValidToken = true;
    } elseif ($new_password !== $confirm_password) {
        $message      = "<div class='alert error'>Passwords do not match!</div>";
        $isValidToken = true;
    } else {
        $check = $pdo->prepare("SELECT email FROM users WHERE reset_token = ? AND reset_expires_at >= NOW()");
        $check->execute([$post_token]);
        $verified = $check->fetch();

        if ($verified) {
            $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE email = ?")
                ->execute([password_hash($new_password, PASSWORD_DEFAULT), $verified['email']]);
            $message = "<div class='alert success'>Password reset successfully! <a href='index.php' style='color:#166534;font-weight:bold;text-decoration:underline;'>Log in now</a>.</div>";
            $isValidToken = false; // Hide form on success
        } else {
            $message = "<div class='alert error'>Password reset session has expired. Please request again!</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset password for online quiz system account.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <title>Reset Password | Online Quiz System</title>
</head>
<body class="auth-page">
<div class="auth-box" style="width:440px; min-height:360px; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:40px;">
    <div style="width:100%; text-align:stretch;">
        <h1 style="margin-bottom:20px; text-align:center;">Create New Password</h1>
        <?= $message ?>
        <?php if ($isValidToken): ?>
            <form method="POST" action="?token=<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" style="width:100%; display:flex; flex-direction:column; align-items:stretch; padding:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password_submit">
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                
                <label style="text-align:left;">New Password</label>
                <input type="password" name="new_password" placeholder="Minimum 6 characters" required style="width:100%; padding:11px;" autocomplete="new-password">
                
                <label style="text-align:left;">Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required style="width:100%; padding:11px;" autocomplete="new-password">
                
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:15px; font-size:13px;">Reset Password</button>
            </form>
        <?php elseif (empty($_POST) || str_contains($message, 'expired')): ?>
            <div style="text-align:center; margin-top:20px;">
                <a href="forgot_password.php" style="font-weight:bold; color:var(--primary); text-decoration:none;">Request a new link</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
