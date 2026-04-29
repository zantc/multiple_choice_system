<?php
session_start();

require 'config.php';

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
            $message = "<div class='alert success'>Password reset successfully! <a href='index.php' style='color:#2e7d32;text-decoration:underline;'>Log in now</a>.</div>";
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
    <link rel="stylesheet" href="style.css">
    <title>Reset Password | Online Quiz System</title>
</head>
<body>
<div class="container" style="width:400px;min-height:350px;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:40px;">
    <div style="width:100%;text-align:center;">
        <h1 style="margin-bottom:20px;">Create New Password</h1>
        <?= $message ?>
        <?php if ($isValidToken): ?>
            <form method="POST" action="?token=<?= htmlspecialchars($token) ?>" style="width:100%;display:flex;flex-direction:column;align-items:center;padding:0;">
                <input type="hidden"   name="reset_token"       value="<?= htmlspecialchars($token) ?>">
                <input type="password" name="new_password"      placeholder="New password (minimum 6 characters)" required style="width:100%;" autocomplete="new-password">
                <input type="password" name="confirm_password"  placeholder="Confirm new password"             required style="width:100%;" autocomplete="new-password">
                <button type="submit" style="width:100%;margin-top:15px;">Reset Password</button>
            </form>
        <?php elseif (empty($_POST)): ?>
            <a href="forgot_password.php" style="display:inline-block;margin-top:20px;">Request a new link</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>